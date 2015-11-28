<?php
namespace Ritopls;

Class Ritopls {
    public $API;

    public function __construct(API $API = NULL) {
        if(!isset($API)) {
            throw new \InvalidArgumentException('Cannot continue without a proper API dependency.');
            return;
        }

        $this->API = $API;
    }

    public function Summoner() {
        $summoner = new Components\Summoner($this->API);
        // Check if everything was A-OK.
        // And now finally return the object.
        return $summoner;
    }

    public function Game() {
        // etc.
    }

    public function __destruct() {
        // TODO: do we even need this?
    }
}

?>
