#PHP WebSockets
A PHP implementation of the WebSocket protocol.

##Quick Start

Install:
```
$ composer require stegru/websockets
```


```php
<?php
// ws.php

...

$wc = new WebSocketServer(8088);

$wc->addMessageListener(function($connection, $message) {
    if ($message === NULL) {
        if (!$connection->isClosed()) {
            $connection->sendMessage("Welcome!");
        }
    } else {
        $connection->sendMessage(strrev($message));
    }
});

$wc->start();

```

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
