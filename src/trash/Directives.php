<?php

namespace Primera;

use Illuminate\View\Compilers\BladeCompiler;
use duncan3dc\Laravel\DirectivesInterface;

class Directives implements DirectivesInterface
{
    /**
     * Register all the active directives to the blade templating compiler.
     *
     * @param BladeCompiler $blade The compiler to extend
     *
     * @return void
     */
    public function register(BladeCompiler $blade): void
    {
        // $blade->component('components.navbar', 'navbar');

        // $blade->include('components.navbar', 'navbar');

        $blade->directive("say", function ($parameter) {
            return "<?php echo {$parameter} ?>";
        });
    }
}
