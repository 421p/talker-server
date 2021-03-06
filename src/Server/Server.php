<?php

namespace Talker\Server;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Response;
use React\Promise\Deferred;
use Talker\Talker\Response\AudioFile;
use Talker\Talker\TalkerInterface;

class Server
{
    private $talker;

    private $loop;

    private $server;

    public function __construct(TalkerInterface $talker, LoopInterface $loop)
    {
        $this->talker = $talker;
        $this->loop = $loop;

        $this->server = $this->createServer();
    }

    private function createServer(): \React\Http\Server
    {
        return new \React\Http\Server(\Closure::fromCallable([$this, 'handleRequest']));
    }

    protected function handleRequest(ServerRequestInterface $request) {
        $body = $request->getParsedBody();

        $defer = new Deferred();

        $this->talker->say($body['text'], $body['locale'])->then(
            function (AudioFile $file) use ($defer) {
                $defer->resolve($this->createResponse($file));
            }
        );

        return $defer->promise();
    }
    
    protected function createResponse(AudioFile $file): Response
    {
        return new Response(
            200,
            ['Content-Type' => 'audio/mpeg'],
            $file->getContent()
        );
    }

    public function listen(string $uri)
    {
        $socket = new \React\Socket\Server($uri, $this->loop);
        $this->server->listen($socket);
    }
}