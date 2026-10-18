<?php
namespace fremeo\core;

class LazyProxy {
    private $resolver;
    private $realInstance = null;

    public function __construct(callable $resolver) {
        $this->resolver = $resolver;
    }

    // Holt die echte Instanz erst bei Bedarf (Lazy)
    private function getInstance() {
        if ($this->realInstance === null) {
            $this->realInstance = ($this->resolver)();
        }
        return $this->realInstance;
    }

    // Fängt Methodenaufrufe ab: $C['xxx']->funktion()
    public function __call($name, $arguments) {
        return call_user_func_array([$this->getInstance(), $name], $arguments);
    }

    // Fängt Eigenschafts-Zugriffe ab (Lesen): $var = $C['xxx']->aktiv
    public function __get($name) {
        return $this->getInstance()->$name;
    }

    // Fängt Eigenschafts-Zuweisungen ab (Schreiben): $C['xxx']->aktiv = true
    public function __set($name, $value) {
        $this->getInstance()->$name = $value;
    }
}
