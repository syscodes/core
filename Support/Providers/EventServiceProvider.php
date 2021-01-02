<?php 

/**
 * Lenevor Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file license.md.
 * It is also available through the world-wide-web at this URL:
 * https://lenevor.com/license
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@Lenevor.com so we can send you a copy immediately.
 *
 * @package     Lenevor
 * @subpackage  Base
 * @author      Javier Alexander Campo M. <jalexcam@gmail.com>
 * @link        https://lenevor.com 
 * @copyright   Copyright (c) 2019-2021 Lenevor Framework 
 * @license     https://lenevor.com/license or see /license.md or see https://opensource.org/licenses/BSD-3-Clause New BSD license
 * @since       0.6.0
 */

namespace Syscodes\Core\Support\Providers;

use Syscodes\Support\Facades\Event;
use Syscodes\Support\ServiceProvider;

/**
 * Manage all events occurred in the application.
 *  
 * @author Javier Alexander Campo M. <jalexcam@gmail.com>
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     * 
     * @var array $listen
     */
    protected $listen = [];

    /**
     * The subscriber classes to register.
     * 
     * @var array $suscribe
     */
    protected $subscribe = [];

    /**
     * Bootstrap any application services.
     * 
     * @return  void
     */
    public function boot()
    {
        foreach ($this->listens() as $event => $listeners)
        {
            foreach ($listeners as $listener)
            {
                Event::listen($event, $listener);
            }
        }
        
        foreach ($this->subscribe as $subscriber)
        {
            Event::subscribe($subscriber);
        }
    }

    /**
     * Load the standard Events file.
     * 
     * @param  string  $path
     * 
     * @return mixed
     */
    protected function loadEventPath($path)
    {
        return require $path;
    }

    /**
     * Get the events and handlers.
     * 
     * @return array
     */
    public function listens()
    {
        return $this->listen;
    }
}