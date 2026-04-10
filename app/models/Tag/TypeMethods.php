<?php

trait TagTypeMethods
{
    public function pretty_type_name()
    {
        return ucfirst($this->type_name);
    }
}
