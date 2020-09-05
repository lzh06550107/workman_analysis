<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Workerman\Events;

/**
 * select eventloop
 */
class Select implements EventInterface
{
    /**
     * All listeners for read/write event.
     *
     * @var array
     */
    public $_allEvents = array();

    /**
     * Event listeners of signal.
     *
     * @var array
     */
    public $_signalEvents = array();

    /**
     * Fds waiting for read event.
     *
     * @var array
     */
    protected $_readFds = array();

    /**
     * Fds waiting for write event.
     *
     * @var array
     */
    protected $_writeFds = array();

    /**
     * Fds waiting for except event.
     *
     * @var array
     */
    protected $_exceptFds = array();

    /**
     * Timer scheduler.
     * {['data':timer_id, 'priority':run_timestamp], ..}
     *
     * @var \SplPriorityQueue
     */
    protected $_scheduler = null; // 仅仅存储任务时间

    /**
     * All timer event listeners.
     * [[func, args, flag, timer_interval], ..]
     *
     * @var array
     */
    protected $_eventTimer = array(); // 存储定时任务实例

    /**
     * Timer id.
     *
     * @var int
     */
    protected $_timerId = 1;

    /**
     * Select timeout.
     *
     * @var int
     */
    protected $_selectTimeout = 100000000;

    /**
     * Paired socket channels
     *
     * @var array
     */
    protected $channel = array();

    /**
     * Construct.
     */
    public function __construct()
    {
        // Init SplPriorityQueue.
        $this->_scheduler = new \SplPriorityQueue(); // 初始化任务调度队列
        $this->_scheduler->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
    }

    /**
     * {@inheritdoc}
     */
    public function add($fd, $flag, $func, $args = array())
    {
        switch ($flag) {
            case self::EV_READ: // 监听读事件
            case self::EV_WRITE: // 监听写事件
                $count = $flag === self::EV_READ ? \count($this->_readFds) : \count($this->_writeFds);
                if ($count >= 1024) {
                    echo "Warning: system call select exceeded the maximum number of connections 1024, please install event/libevent extension for more connections.\n";
                } else if (\DIRECTORY_SEPARATOR !== '/' && $count >= 256) {
                    echo "Warning: system call select exceeded the maximum number of connections 256.\n";
                }
                $fd_key                           = (int)$fd;
                $this->_allEvents[$fd_key][$flag] = array($func, $fd); // 保存事件监听器
                if ($flag === self::EV_READ) {
                    $this->_readFds[$fd_key] = $fd;
                } else {
                    $this->_writeFds[$fd_key] = $fd;
                }
                break;
            case self::EV_EXCEPT: // 带外事件
                $fd_key = (int)$fd;
                $this->_allEvents[$fd_key][$flag] = array($func, $fd); // 保存事件监听器
                $this->_exceptFds[$fd_key] = $fd;
                break;
            case self::EV_SIGNAL: // 信号处理
                // Windows not support signal.
                if(\DIRECTORY_SEPARATOR !== '/') {
                    return false;
                }
                $fd_key                              = (int)$fd;
                $this->_signalEvents[$fd_key][$flag] = array($func, $fd);
                \pcntl_signal($fd, array($this, 'signalHandler')); // 安装信号处理器
                break;
            case self::EV_TIMER: // 定时器事件
            case self::EV_TIMER_ONCE:
                $timer_id = $this->_timerId++;
                $run_time = \microtime(true) + $fd; // 当前时间+运行时间=任务运行的时间
                $this->_scheduler->insert($timer_id, -$run_time); // 优先级队列，因为是最大堆，所以需要转换为负值，让最近的时间最先出队列
                $this->_eventTimer[$timer_id] = array($func, (array)$args, $flag, $fd);
                // 把select超时时间设置为任务到期时间，这样会在超时时间到时结束select阻塞来执行tick任务
                $select_timeout = ($run_time - \microtime(true)) * 1000000;
                if( $this->_selectTimeout > $select_timeout ){ 
                    $this->_selectTimeout = $select_timeout;   
                }  
                return $timer_id; // 任务id
        }

        return true;
    }

    /**
     * Signal handler.
     *
     * @param int $signal
     */
    public function signalHandler($signal)
    {
        \call_user_func_array($this->_signalEvents[$signal][self::EV_SIGNAL][0], array($signal));
    }

    /**
     * {@inheritdoc}
     */
    public function del($fd, $flag)
    {
        $fd_key = (int)$fd;
        switch ($flag) {
            case self::EV_READ: // 删除指定fd的读事件监听器
                unset($this->_allEvents[$fd_key][$flag], $this->_readFds[$fd_key]);
                if (empty($this->_allEvents[$fd_key])) {
                    unset($this->_allEvents[$fd_key]);
                }
                return true;
            case self::EV_WRITE: // 删除指定fd的写事件监听器
                unset($this->_allEvents[$fd_key][$flag], $this->_writeFds[$fd_key]);
                if (empty($this->_allEvents[$fd_key])) {
                    unset($this->_allEvents[$fd_key]);
                }
                return true;
            case self::EV_EXCEPT: // 删除指定fd的带外事件监听器
                unset($this->_allEvents[$fd_key][$flag], $this->_exceptFds[$fd_key]);
                if(empty($this->_allEvents[$fd_key]))
                {
                    unset($this->_allEvents[$fd_key]);
                }
                return true;
            case self::EV_SIGNAL: // 进程结束信号处理
                if(\DIRECTORY_SEPARATOR !== '/') {
                    return false; // 如果不是linux，则返回
                }
                unset($this->_signalEvents[$fd_key]);
                \pcntl_signal($fd, SIG_IGN); //因为并发服务器常常fork很多子进程，子进程终结之后需要服务器进程去wait清理资源。如果将此信号的处理方式设为忽略，可让内核把僵尸子进程转交给init进程去处理，省去了大量僵尸进程占用系统资源。
                break;
            case self::EV_TIMER:
            case self::EV_TIMER_ONCE;
                unset($this->_eventTimer[$fd_key]);
                return true;
        }
        return false;
    }

    /**
     * Tick for timer. tick是一个非常小的时间单位
     *
     * @return void
     */
    protected function tick()
    {
        while (!$this->_scheduler->isEmpty()) { // 如果任务调度队列非空，则不断取出到时间的任务
            $scheduler_data       = $this->_scheduler->top();
            $timer_id             = $scheduler_data['data'];
            $next_run_time        = -$scheduler_data['priority']; // 转换为正常时间
            $time_now             = \microtime(true);
            // 设置select超时时间为最近马上要执行的任务时间间隔
            $this->_selectTimeout = ($next_run_time - $time_now) * 1000000;
            if ($this->_selectTimeout <= 0) { // 已经超过任务要执行的时间
                $this->_scheduler->extract(); // 从队列取出任务

                if (!isset($this->_eventTimer[$timer_id])) { // 查看是否存在任务实体
                    continue; // 不存在就跳过
                }

                // [func, args, flag, timer_interval]
                $task_data = $this->_eventTimer[$timer_id]; // 存在，则取出任务实体
                if ($task_data[2] === self::EV_TIMER) { // 如果是周期定时任务，则
                    $next_run_time = $time_now + $task_data[3]; // 下次运行时间=当前时间+时间间隔
                    $this->_scheduler->insert($timer_id, -$next_run_time); // 再次插入任务调度队列
                }
                \call_user_func_array($task_data[0], $task_data[1]); // 传入指定参数来调度任务函数
                // 如果是一次定时任务，则从_eventTimer中删除该任务实体
                if (isset($this->_eventTimer[$timer_id]) && $task_data[2] === self::EV_TIMER_ONCE) {
                    $this->del($timer_id, self::EV_TIMER_ONCE);
                }
                continue; // 继续查看是否还有到期的任务
            }
            return; // 如果没有到时间的任务，则退出循环
        }
        $this->_selectTimeout = 100000000; // 如果任务为空，则设置默认select超时时间
    }

    /**
     * {@inheritdoc}
     */
    public function clearAllTimer()
    {
        $this->_scheduler = new \SplPriorityQueue();
        $this->_scheduler->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
        $this->_eventTimer = array();
    }

    /**
     * {@inheritdoc}
     */
    public function loop()
    {
        while (1) {
            if(\DIRECTORY_SEPARATOR === '/') { // 如果是linux系统
                // Calls signal handlers for pending signals
                \pcntl_signal_dispatch(); // 分发信号来调用信号处理器
            }

            $read  = $this->_readFds; // 需要监听读的fd
            $write = $this->_writeFds; // 需要监听写的fd
            $except = $this->_exceptFds; // 需要监听高优先级的带外数据的fd

            if ($read || $write || $except) {
                // Waiting read/write/signal/timeout events.
                try {
                    $ret = @stream_select($read, $write, $except, 0, $this->_selectTimeout);
                } catch (\Exception $e) {} catch (\Error $e) {}

            } else {
                usleep($this->_selectTimeout);
                $ret = false;
            }


            if (!$this->_scheduler->isEmpty()) { // 是否存在调度任务
                $this->tick(); // 存在，则调度执行tick任务
            }

            if (!$ret) { // 不存在事件发生，则进入下一个循环
                continue;
            }

            if ($read) { // 存在读事件
                foreach ($read as $fd) {
                    $fd_key = (int)$fd;
                    // 如果该fd存在READ事件监听器，则调用
                    if (isset($this->_allEvents[$fd_key][self::EV_READ])) {
                        \call_user_func_array($this->_allEvents[$fd_key][self::EV_READ][0],
                            array($this->_allEvents[$fd_key][self::EV_READ][1]));
                    }
                }
            }

            if ($write) { // 存在写事件
                foreach ($write as $fd) {
                    $fd_key = (int)$fd;
                    // 如果该fd存在WRITE事件监听器，则调用
                    if (isset($this->_allEvents[$fd_key][self::EV_WRITE])) {
                        \call_user_func_array($this->_allEvents[$fd_key][self::EV_WRITE][0],
                            array($this->_allEvents[$fd_key][self::EV_WRITE][1]));
                    }
                }
            }

            if($except) { // 带外数据
                foreach($except as $fd) {
                    $fd_key = (int) $fd;
                    // 如果该fd存在带外数据监听器，则调用
                    if(isset($this->_allEvents[$fd_key][self::EV_EXCEPT])) {
                        \call_user_func_array($this->_allEvents[$fd_key][self::EV_EXCEPT][0],
                            array($this->_allEvents[$fd_key][self::EV_EXCEPT][1]));
                    }
                }
            }
        }
    }

    /**
     * Destroy loop.
     *
     * @return void
     */
    public function destroy()
    {

    }

    /**
     * Get timer count.
     *
     * @return integer
     */
    public function getTimerCount()
    {
        return \count($this->_eventTimer);
    }
}
