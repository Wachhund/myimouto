<?php

class StaticHelper extends Rails\ActionView\Helper
{
    public function numbers_to_imoutos($number)
    {
        if (!CONFIG()->show_homepage_imoutos) {
            return;
        }

        $number = str_split($number);
        $output = '<div style="margin-bottom: 1em;">' . "\r\n";

        foreach ($number as $num) {
            $output .=	'    <img alt="' . $num . '" src="/images/' . $num . ".gif\" />\r\n";
        }

        $output .= "  </div>\r\n";
        return $output;
    }
}
