<?php require __DIR__.'/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

define('APP_PORT', 8888);

date_default_timezone_set('Europe/Paris');

class ServerImpl implements MessageComponentInterface {
    protected $clients;
    private $connexions = [];

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {

        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId}).\n";
        $this->connexions[$conn->resourceId] = "";
    }

    public function onMessage(ConnectionInterface $conn, $msg) {
        echo sprintf("New message from '%s': %s\n\n\n", $conn->resourceId, $msg);
        $message = json_decode($msg, true);
        $message["date"] = date('m/d/Y h:i:s', time());
        $message["id"] = $conn->resourceId;
        foreach ($this->clients as $client) { // BROADCAST
            
            if ($conn !== $client) {
                $message["isSender"] = false;
                $client->send(json_encode($message));
            }else{
                $message["isSender"] = true;
                $client->send(json_encode($message));
                $this->connexions[$conn->resourceId] = $message["sender"];
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        $this->sayGoodby($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error occured on connection {$conn->resourceId}: {$e->getMessage()}\n\n\n";
        $conn->close();
    }

    public function sayGoodby(ConnectionInterface $conn){
        $message = "User <b>{$this->connexions[$conn->resourceId]}</b> est parti ...";
        $data = [
            "sender" => "le serveur vous informe !",
            "message" => $message,
            "isSender" => false,
            "date" =>  date('m/d/Y hh:mm:ss', time()),
        ];
        foreach ($this->clients as $client) { // BROADCAST
            if ($conn !== $client) {
                $client->send(json_encode($data));
            }
        }
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ServerImpl()
        )
    ),
    APP_PORT
);
echo "Server created on port " . APP_PORT . "\n\n";
$server->run();
