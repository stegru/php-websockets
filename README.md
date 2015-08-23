#PHP WebSockets
A PHP implementation of the WebSocket protocol.

##Quick Start

Install:
```
$ composer require stegru/websockets
```

Simple example:
```php
<?php
require_once('vendor/autoload.php');

use WebSockets\Common\Event;
use WebSockets\Server\WebSocketServer;

$ws = new WebSocketServer();

$ws->addEventListener(function (Event $e) {

    switch ($e->eventId) {
        case WebSocketServer::EVENT_CONNECTED:
            $e->connection->sendMessage("Welcome!");
            break;

        case WebSocketServer::EVENT_MESSAGE:
            $e->connection->sendMessage(strrev($e->message));
            break;
    }
});

$ws->start();
```

Run:
```
$ php ws.php 
```

Browser:
```javascript
var ws = new WebSocket("ws://localhost:8088/socketserver", "hello");
ws.onmessage = function(e) { console.log(e.data); }
ws.send("hello!");
```

Output:
```
Welcome!
!olleh
```
