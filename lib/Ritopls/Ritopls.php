<?php
namespace Ritopls;

Class Ritopls {
    public $API;

    /**
     * @param API|NULL $API API Instance.
     * Sets the API instance.
     */
    public function __construct(API $API = NULL) {
        if(!isset($API)) {
            throw new \InvalidArgumentException('Cannot continue without a proper API dependency.');
            return;
        }

        $this->API = $API;
    }

    /**
     * @return Components\Summoner
     * Returns the Summoner object for chaining.
     */
    public function Summoner() {
        return new Components\Summoner($this->API);
    }

    /**
     * @return Components\Game
     * Returns the Game object for chaining.
     */
    public function Game() {
        // to be added
    }

    public function __destruct() {
        // TODO: do we even need this?
    }
}

?>
