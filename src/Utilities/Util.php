<?php

namespace ErnestoVargas\Generators\Utilities;

/**
 * Class Util.
 */
class Util
{
    /**
     * Convert an underscored table name to an uppercased class name.
     *
     * @param $table
     *
     * @return mixed
     */
    public static function Table2ClassName($table)
    {
        $string = str_replace(' ', '', ucwords(str_replace('_', ' ', $table)));

        return $string;
    }

    /**
     * Convert a PHP array into a string version.
     *
     * @param $array
     *
     * @return string
     */
    public static function Array2String($array)
    {
        $string = '[';
        if (!empty($array)) {
            $string .= "'";
            $string .= implode("', '", $array);
            $string .= "'";
        }
        $string .= ']';

        return $string;
    }

    /**
     * Convert a boolean into a string.
     *
     * @param $boolean
     *
     * @return string true|false
     */
    public static function Boolean2String($boolean)
    {
        $string = $boolean ? 'true' : 'false';

        return $string;
    }
}
