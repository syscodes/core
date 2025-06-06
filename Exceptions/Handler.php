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
 * @link        https://lenevor.com
 * @copyright   Copyright (c) 2019 - 2025 Alexander Campo <jalexcam@gmail.com>
 * @license     https://opensource.org/licenses/BSD-3-Clause New BSD license or see https://lenevor.com/license or see /license.md
 */

namespace Syscodes\Components\Core\Exceptions;

use Exception;
use Throwable;
use Psr\Log\LoggerInterface;
use Syscodes\Components\Support\Arr;
use Syscodes\Components\Http\Response;
use Syscodes\Components\Routing\Router;
use Syscodes\Components\Support\ViewErrorBag;
use Syscodes\Components\Http\RedirectResponse;
use Syscodes\Components\Debug\ExceptionHandler;
use Syscodes\Components\Contracts\Container\Container;
use Syscodes\Components\Contracts\Core\ExceptionRender;
use Syscodes\Components\Core\Http\Exceptions\HttpException;
use Syscodes\Components\Http\Exceptions\HttpResponseException;
use Syscodes\Components\Debug\FatalExceptions\FlattenException;
use Syscodes\Components\Auth\Exceptions\AuthenticationException;
use Syscodes\Components\Session\Exceptions\TokenMismatchException;
use Syscodes\Components\Core\Http\Exceptions\NotFoundHttpException;
use Syscodes\Components\Database\Erostrine\Exceptions\ModelNotFoundException;
use Syscodes\Components\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;

/**
 * The system's main exception class is loaded for activate the render method of debugging.
 */
class Handler implements ExceptionHandlerContract
{
    /**
     * The container implementation.
     * 
     * @var \Syscodes\Contracts\Container\Container $container
     */
    protected $container;

    /**
     * A list of the exception types that should not be reported.
     * 
     * @var array $dontReport
     */
    protected $dontReport = [];

    /**
     * A list of the Core exception types that should not be reported.
     * 
     * @var array $coreDontReport
     */
    protected $coreDontReport = [
        AuthenticationException::class,
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        TokenMismatchException::class,
    ];

    /**
     * The callbacks that should be used during reporting.
     * 
     * @var array $reportCallbacks
     */
    protected $reportCallbacks = [];

    /**
     * The callbacks that should be used during rendering.
     * 
     * @var array $renderCallbacks
     */
    protected $renderCallbacks = [];

    /**
     * Constructor. Create a new exception handler instance.
     * 
     * @param  \Syscodes\Components\Contracts\Container\Container  $container
     * 
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->register();
    }

    /**
     * Register the exception handling with callbacks for the application.
     * 
     * @return void
     */
    public function register() {}

    /**
     * Register a reportable callback.
     * 
     * @param  \callable  $callback
     * 
     * @return static
     */
    public function reportable(callable $callback): static
    {
        $this->reportCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a renderable callback.
     * 
     * @param  \callable  $callback
     * 
     * @return static
     */
    public function renderable(callable $callback): static
    {
        $this->renderCallbacks[] = $callback;

        return $this;
    }
    
    /**
     * Report or log an exception.
     * 
     * @param  \Throwable  $e
     * 
     * @return mixed
     * 
     * @throws \Exception
     */
    public function report(Throwable $e)
    {
        if ($this->shouldntReport($e)) {
            return;
        }

        if (method_exists($e, 'report')) {
            return $e->report($e);
        }
        
        foreach ($this->reportCallbacks as $reportCallback) {
            if ($reportCallback($e) === false) {
                return;
            }
        }

        try {
            $logger = $this->container->make(LoggerInterface::class);
        } catch (Exception $e) {
            throw $e;
        }
        
        $logger->error($e->getMessage());
    }

    /**
     * Determine if the exception should be reported.
     * 
     * @param  \Throwable  $e
     * 
     * @return bool
     */
    public function shouldReport(Throwable $e): bool
    {
        return ! $this->shouldntReport($e);
    }

    /**
     * Determine if the exception is in the "do not report" list.
     * 
     * @param  \Throwable  $e
     * 
     * @return bool
     */
    public function shouldntReport(Throwable $e): bool
    {
        $dontReport = array_merge($this->dontReport, $this->coreDontReport);
        
        return ! is_null(Arr::first($dontReport, fn ($type) => $e instanceof $type));
    }

    /**
     * Render an exception into an HTTP response.
     * 
     * @param  \Syscodes\Components\Http\Request  $request
     * @param  \Throwable  $e
     * 
     * @return \Syscodes\Components\Http\Response
     */
    public function render($request, Throwable $e)
    {
        if (method_exists($e, 'render') && $response = $e->render($request)) {
            return Router::toResponse($request, $response);
        }
        
        $e = $this->prepareException($e);
        
        foreach ($this->renderCallbacks as $renderCallback) {
            $response = $renderCallback($e, $request);
            
            if ( ! is_null($response)) {
                return $response;
            }
        }

        foreach ($this->renderCallbacks as $renderCallback) {
            $response = $renderCallback($e, $request);

            if ( ! is_null($response)) {
                return $response;
            }
        }

        if ($e instanceof HttpResponseException) {
            $e->getResponse();
        }

        return $this->prepareResponse($request, $e);
    }

    /**
     * Prepare exception for rendering.
     * 
     * @param  Throwable  $e
     * 
     * @return Throwable
     */
    protected function prepareException(Throwable $e): Throwable
    {
        return match (true) {            
            $e instanceof ModelNotFoundException => new NotFoundHttpException($e->getMessage(), $e),
            $e instanceof TokenMismatchException => new HttpException(419, $e->getMessage(), $e),
            default => $e,
        };
    }
     
    /**
     * Prepare a response for the given exception.
     * 
     * @param  \Syscodes\Components\Http\Request  $request
     * @param  Throwable  $e
     * 
     * @return \Syscodes\Components\Http\Response
     * 
     * @uses   \Syscodes\Components\Core\Http\Exceptions\HttpException
     */
    protected function prepareResponse($request, Throwable $e)
    {
        if ( ! $this->isHttpException($e) && config('app.debug')) {
            return $this->toSyscodesResponse($this->convertExceptionToResponse($e), $e);
        }

        // When the debug is not active, the HTTP 500 code view is throw
        if ( ! $this->isHttpException($e)) {
            $e = new HttpException(500, $e->getMessage());
        }

        return $this->toSyscodesResponse(
                    $this->renderHttpException($e), $e
                )->prepare($request);
    }

    /**
     * Render the given HttpException.
     * 
     * @param  \Syscodes\Components\Core\Http\Exceptions\HttpException  $e
     * 
     * @return \Syscodes\Components\Http\Response
     */
    protected function renderHttpException(HttpException $e)
    {
        $this->registerViewErrorPaths();

        if ($view = $this->getHttpExceptionView($e)) {
            try {
                return response()->view($view, [
                        'errors' => new ViewErrorBag,
                        'exception' => $e,
                ], $e->getStatusCode(), $e->getHeaders());
            } catch (Throwable $th) {
                config('app.debug') && throw $th;

                $this->report($th);
            }
        }

        return $this->convertExceptionToResponse($e);
    }

    /**
     * Register the error view paths.
     * 
     * @return void
     */
    protected function registerViewErrorPaths(): void
    {
        (new RegisterErrorViewPaths)();
    }

    /**
     * Get the view used to render HTTP exceptions.
     * 
     * @param  \Syscodes\Components\Core\Http\Exceptions\HttpException  $e
     * 
     * @return string|null
     */
    protected function getHttpExceptionView(HttpException $e): string|null
    {
        $view = 'errors::'.$e->getStatusCode();

        if (view()->exists($view)) {
            return $view;
        }

        return null;
    }

    /**
     * Create a response for the given exception.
     * 
     * @param  Throwable  $e
     * 
     * @return \Syscodes\Components\Http\Response
     */
    protected function convertExceptionToResponse(Throwable $e)
    {
        return Response::render(
            $this->renderExceptionContent($e),
            $this->isHttpException($e) ? $e->getStatusCode() : 500,
            $this->isHttpException($e) ? $e->getHeaders() : []
        );
    }

    /**
     * Gets the response content for the given exception.
     * 
     * @param  Throwable  $e
     * 
     * @return string
     */
    protected function renderExceptionContent(Throwable $e)
    {
        try {
            if (config('app.debug')) {
                if (app()->has(ExceptionRender::class)) {
                    return $this->renderExceptionWithDebug($e);
                }
            }
            
            return $this->renderExceptionWithFlatDesignDebug($e, config('app.debug'));
        } catch (Exception $e) {
            return $this->renderExceptionWithFlatDesignDebug($e, config('app.debug'));
        }
    }

    /**
     * Render an exception to a string of debug.
     * 
     * @param  Throwable  $e
     * 
     * @return void
     * 
     * @uses   \Syscodes\Components\Contracts\Core\ExceptionRender
     */
    protected function renderExceptionWithDebug(Throwable $e)
    {
        return app(ExceptionRender::class)->render($e);
    }

    /**
     * Render an exception to a string using Flat Design Debug.
     * 
     * @param  Throwable  $e
     * @param  bool  $debug
     * 
     * @return string
     */
    protected function renderExceptionWithFlatDesignDebug(Throwable $e, $debug)
    {
        return (new ExceptionHandler($debug))->getHtmlResponse(
            FlattenException::make($e)
        );
    }

    /**
     * Map the given exception into an Syscodes response.
     * 
     * @param  \Syscodes\Components\Http\Response  $response
     * @param  Throwable  $e 
     * 
     * @return \Syscodes\Components\Http\Response
     */
    protected function toSyscodesResponse($response, Throwable $e)
    {
        if ($response instanceof RedirectResponse) {
            $response = new RedirectResponse(
                $response->getTargetUrl(), $response->status(), $response->headers->all()
            );
        } else {
            $response = new Response(
                $response->content(), $response->status(), $response->headers->all()
            );
        }

        return $response->withException($e);
    }

    /**
     * Render an exception to the console.
     * 
     * @param  \Syscodes\Components\Contracts\Console\Output\ConsoleOutput  $output
     * @param  Throwable  $e
     * 
     * @return void
     */
    public function renderForConsole($output, Throwable $e)
    {
        $message = sprintf(
            $output->write("<error>".getClass($e, true)."</>")." %s in file %s on line %d\n\n%s\n",
            //getClass($e, true),
            $e->getMessage(),            
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        echo $message;
    }

    /**
     * Determine if the given exception is an HTTP exception.
     * 
     * @param  Throwable  $e
     * 
     * @return bool
     */
    protected function isHttpException(Throwable $e): bool
    {
        return $e instanceof HttpException;
    }
}