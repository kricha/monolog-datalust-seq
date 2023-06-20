<?php

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER' => true,
        '@Symfony' => true,
    ])
    // ->setIndent("\t")
    ->setLineEnding("\n")
;
