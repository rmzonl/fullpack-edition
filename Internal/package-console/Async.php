<?php namespace ZN\Console;
/**
 * ZN PHP Web Framework
 * 
 * "Simplicity is the ultimate sophistication." ~ Da Vinci
 * 
 * @package ZN
 * @license MIT [http://opensource.org/licenses/MIT]
 * @author  Ozan UYKUN [ozan@znframework.com]
 */

use Throwable;
use ZN\Base;
use ZN\Config;
use ZN\Buffering;
use ZN\Filesystem;
use ZN\Protection\Json;
use ZN\Helpers\Converter;
use ZN\ErrorHandling\Errors;
use ZN\ErrorHandling\Exceptions;

/**
 * @codeCoverageIgnore
 */
class Async
{
    /**
     * Keeps process ID.
     * 
     * @var string $procId
     */
    protected static $procId = '';

    /**
     * Keeps process directory.
     * 
     * @var string $procDir
     */
    protected static $procDir = FILES_DIR;

    /**
     * Set process directory.
     * 
     * @param string $directory
     * 
     * @return self
     */
    public static function setProcDirectory(string $directory) : Async
    {
        self::$procDir = $directory;

        return new self;
    }

    /**
     * Get process data.
     * 
     * @param string $procId = current-process
     * 
     * @return array
     */
    public static function getData(string $procId = '') : array
    {
        $procFile = self::getProcFile($procId);

        if( is_file($procFile) )
        {
            return Json::decodeArray(file_get_contents($procFile));
        }

        return [];
    }

    /**
     * Get process Id.
     */
    public static function getProcId() : string
    {
        return self::$procId;
    }

    /**
     * Run command
     * 
     * @param string $command
     * @param array  $data = []
     * @param string $name = NULL
     */
    public static function run(string $command, array $data = [], string $name = NULL) : string
    {
        self::$procId = $procId = self::$procDir . ($uniq = $name ? preg_replace('/\W+/', '', $name) : uniqid());

        $processor = Config::default('ZN\Prompt\PromptDefaultConfiguration')::get('Services', 'processor');

        if( ! file_exists($processor['path']) ) 
        {
            $path = 'php';
        }
        else
        {
            $path = $processor['path'];
        }

        $open = proc_open($path . ' zerocore ' . $command . ' "' . $procId . '"', [], $arr);

        $data['status'] = proc_get_status($open);

        $data['status']['run']      = $command;
        $data['status']['file']     = $uniq;
        $data['status']['path']     = self::$procId;
        
        file_put_contents($procId, Json::encode($data));

        return $procId;
    }

    /**
     * List
     * 
     * @return array
     */
    public static function list() : array
    {
        $processList = [];

        foreach( Filesystem::getFiles(self::$procDir, NULL, true) as $file )
        {
            $processList[] = self::getData($file);
        }

        return $processList;
    }

    /**
     * Close proc
     * 
     * @param string $procId = current-process
     * 
     * @return string|false
     */
    public static function close(string $procId = '')
    { 
        if( $pid = self::getData($procId)['status']['pid'] ?? NULL )
        {
            self::remove($procId);

            return self::closeProcess($pid);
        }

        return false;
    }

    /**
     * Close All Process
     *
     */
    public static function closeAll()
    {
        foreach( self::list() as $proc )
        {
            if( isset($proc['file']) )
            {
                self::close($proc['file']);
            }
        }
    }

    /**
     * Is Exists
     * 
     * @param string $procId = current-process
     * 
     * @return bool
     */
    public static function isExists(string $procId = '') : bool
    {
        $procFile = self::getProcFile($procId);

        return is_file($procFile);
    }

    /**
     * Command process
     * 
     * @param string   $procId
     * @param callback $callable
     * @param bool     $displayError = false
     */
    public static function process(string $procId, callable $callable, bool $displayError = false) : void
    {
        self::$procId = $procId;

        $data = self::getData($procId);

        try
        {
            $callable($data, $procId);
        }
        catch( Throwable $e )
        {
            if( $displayError )
            {
                $error = 
                [
                    'code'    => $e->getCode(),
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTrace()
                ];

                self::report($error, 'error');
            }
        }

        self::close();
    }
    
    /**
     * Remove process ID
     * 
     * @param string $procId = current-process
     * 
     * @return bool
     */
    public static function remove(string $procId = '') : bool
    {
        $procFile = self::getProcFile($procId);

        if( is_file($procFile) )
        {
            return unlink($procFile);
        }

        return false;
    }
    
    /**
     * Create report
     * 
     * @param array $data
     * 
     * @return int
     */
    public static function report(array $data, string $suffix = 'report') : int
    {
        return file_put_contents(self::$procId . '-' . $suffix, Json::encode($data));
    }

    /**
     * Status
     * 
     * @param string ...$procIds
     * 
     * @return array
     */
    public static function status(string ...$procIds) : array
    {
        $pending = [];
        
        foreach( $procIds as $procId )
        {
            $procFile = self::getProcFile($procId);

            if( is_file($procFile) )
            {
                $pending[$procFile] = 1; # pending.
            }
        }

        return $pending;
    }

    /**
     * Is finish
     * 
     * @param string ...$procIds
     * 
     * @return bool
     */
    public static function isFinish(string ...$procIds) : bool
    {
        return  ! in_array(1, self::status(...$procIds));
    }

    /**
     * Dispay Report
     * 
     * @param string $errorFile
     * 
     * @return string
     */
    public static function displayError(string $errorFile) : string
    {
        if( is_file($file = self::$procDir . $errorFile) && ( $fileContent = file_get_contents($file) ) ) 
        {
            $data = Json::decodeArray($fileContent);

            return Buffering\Callback::do(function() use($data)
            { 
                Exceptions::table($data['code'] ?? NULL, $data['message'], $data['file'], $data['line'], $data['trace']); 
            });
        }
        
        return Errors::message('File not found!');
    }

    /**
     * Loop
     * 
     * @param int $count
     * @param int $waitSecond
     * @param callable $callback
     */
    public static function loop(int $count, int $waitSecond, callable $callback)
    {
        $i = 1;

        while( true )
        {
            $callback($i, $waitSecond);

            if( $i == $count )
            {
                self::close();
            }

            $i++;

            sleep($waitSecond);
        }
    } 

    /**
     * Is Run
     * 
     * @param string $procId = current-process
     * 
     * @return bool
     */
    public static function isRun(string $procId = '') : bool
    {
        if( $pid = self::getData($procId)['status']['pid'] ?? NULL )
        {
            if( stripos(php_uname('s'), 'win') > -1 ) 
            {
                $output = [];
    
                exec("tasklist /FI \"PID eq $pid\"", $output);
                
                foreach( $output as $line) 
                {
                    if( strpos($line, (string)$pid) !== false ) 
                    {
                        return true;
                    }
                }
            } 
            else 
            {
                exec("ps -p $pid", $output);
                
                if( count($output) > 1 ) 
                {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * protected get proc file
     */
    protected static function getProcFile(string $procId = '')
    {
        return $procId ? Base::prefix($procId, self::$procDir) : self::$procId;
    }

    /**
     * protected close process
     */
    protected static function closeProcess($pid)
    {
        return stripos(php_uname('s'), 'win') > -1 ? exec("taskkill /F /T /PID $pid") : exec("kill -9 $pid");
    }
}
