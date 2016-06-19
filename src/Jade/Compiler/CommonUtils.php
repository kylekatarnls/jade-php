<?php

namespace Jade\Compiler;

/**
 * Class Jade CommonUtils.
 * Common static methods for compiler and lexer classes.
 */
class CommonUtils
{
    /**
     * @param string $call
     *
     * @throws \Exception
     *
     * @return string
     */
    public static function addDollarIfNeeded($call)
    {
        if ($call === 'Inf') {
            throw new \Exception($call . ' cannot be read from PHP', 1);
        }
        if ($call === 'undefined') {
            return 'null';
        }
        if ($call[0] !== '$' && $call[0] !== '\\' && !preg_match('#^(?:' . CompilerConfig::VARNAME . '\\s*\\(|(?:null|false|true)(?![a-z]))#i', $call)) {
            $call = '$' . $call;
        }

        return $call;
    }
}
