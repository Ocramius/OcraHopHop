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

use Zend\Http\Client;
use Zend\Http\Request;
use Zend\Uri\Uri;
use Zend\Http\PhpEnvironment\Request as EnvironmentRequest;
use Zend\Http\PhpEnvironment\Response as EnvironmentResponse;

/**
 * Lightweight request dispatcher that uses a worker to
 * get an HTTP response
 *
 * @author  Marco Pivetta <ocramius@gmail.com>
 * @license MIT
 */
class Dispatcher
{
    /**
     * @var Uri to the worker
     */
    protected $workerUri;

    /**
     * @param string $workerUri URI to the worker that should dispatch the request
     */
    public function __construct($workerUri = 'http://localhost:1337')
    {
        $this->workerUri = new Uri($workerUri);
    }

    /**
     * Serves the current environment HTTP request by throwing it at the configured
     * worker. This is much like how FastCGI works, but implemented in PHP.
     *
     * @return EnvironmentResponse the response object as computed by the worker
     */
    public function dispatch()
    {
        $start = microtime(true);
        $originalRequest    = new EnvironmentRequest();
        $client             = new Client();
        $request            = new Request();

        $request->setMethod(\Zend\Http\Request::METHOD_GET);
        $request->setUri($this->workerUri);
        $request->setContent(json_encode(array(
            'original_request_string' => $originalRequest->toString(),
            'original_base_url'       => $originalRequest->getBaseUrl(),
            'original_base_path'      => $originalRequest->getBasePath(),
        )));

        $response             = $client->dispatch($request);
        $originalResponseData = json_decode($response->getContent(), true);

        $httpResponse = EnvironmentResponse::fromString($originalResponseData['original_response_string']);
        //die($originalResponseData['microtime'] . ' | ' . (microtime(true) - $start) . PHP_EOL);

        return $httpResponse;
    }
}
