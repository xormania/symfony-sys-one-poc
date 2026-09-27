<?php

return (new PhpCsFixer\Config())
    ->setRules(['@Symfony' => true, 'declare_strict_types' => true])
    ->setRiskyAllowed(true)
    ->setFinder(PhpCsFixer\Finder::create()->in([__DIR__.'/src', __DIR__.'/config', __DIR__.'/tests'])->notPath('reference.php')->append([__DIR__.'/bin/console']));
