<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace OcraHopHop;

use Zend\Mvc\Application as Application;
use Zend\Mvc\MvcEvent;
use React\Http\Request as ReactRequest;
use React\Http\Response as ReactResponse;
use React\EventLoop\Factory as EventLoopFactory;
use React\Socket\Server as SocketServer;
use React\Http\Server as HttpServer;
use Zend\Http\Request;
use Zend\Http\Response;

/**
 * Basic application wrapper for a Zend Framework 2 Application.
 * It bootstraps an existing application and then runs it once per request.
 * This is much faster than creating and bootstrapping the application
 * at each request.
 *
 *
 * @author  Marco Pivetta <ocramius@gmail.com>
 * @license MIT
 */
class ApplicationWorker
{
    /**
     * @var Application
     */
    protected $application;

    /**
     * @var int
     */
    protected $servedRequests = 0;

    /**
     * @var int
     */
    protected $port;

    /**
     * @param Application $application
     * @param integer     $port        port which the worker should listen for incoming requests
     */
    public function __construct(Application $application, $port = 1337)
    {
        $this->application = $application;

        $this->application->getEventManager()->attach(
            MvcEvent::EVENT_FINISH,
            function() {
                // disabling the default attached response send listener
                return false;
            },
            -9999
        );

        $this->application->bootstrap();
        $this->port = (int) $port;
    }

    /**
     * Starts listening for incoming HTTP requests.
     *
     * Each incoming HTTP request should have a JSON-encoded body with a
     * valid `original_request_string` to be passed
     * to {@see \Zend\Http\Request::fromString}
     *
     * The worker will reply to incoming HTTP requests with a JSON-encoded
     * string containing an `original_response_string` to be passed
     * to {@see \Zend\Http\Response::fromString}
     *
     * @return void
     */
    public function run()
    {
        $application = $this;

        $app = function (ReactRequest $reactRequest, ReactResponse $reactResponse) use ($application) {
            $reactRequest->on('data', function ($data) use ($application, $reactResponse) {
                $application->servedRequests += 1;
                $data = json_decode($data, true);

                $request = Request::fromString($data['original_request_string']);
                $response = new Response();
                $mvcEvent = $application->application->getMvcEvent();

                $mvcEvent->setRequest($request);
                $mvcEvent->setResponse($response);

                /* @var $response \Zend\Http\Response */
                $response = $application->application->run();
                $content = json_encode(array(
                    'served_requests_count'    => $application->servedRequests,
                    'original_response_string' => $response->toString(),
                ));

                $reactResponse->writeHead(200, array('Content-Length' => mb_strlen($content)));

                $reactResponse->end(json_encode(array(
                    'served_requests_count'    => $application->servedRequests,
                    'original_response_string' => $response->toString(),
                )));
            });
        };

        $loop   = EventLoopFactory::create();
        $socket = new SocketServer($loop);
        $http   = new HttpServer($socket, $loop);

        $http->on('request', $app);
        $socket->listen($this->port);
        $loop->run();
    }
}