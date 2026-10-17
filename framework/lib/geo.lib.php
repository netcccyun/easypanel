<?php
class EpGeo
{
    public static function available()
    {
        $access = new Access(null, 'request');
        $tables = $access->listTable();
        if ($tables === false) { return false; }
        foreach ($tables as $name) {
            $chains = $access->listChain($name, 1);
            if ($chains && self::hasGeo($chains)) { return true; }
        }
        return false;
    }

    public static function hasGeo($chains)
    {
        foreach ($chains->children() as $chain) {
            foreach ($chain->children() as $model) {
                if ($model->getName() === 'mark_geo' ||
                    ($model->getName() === 'mark' && (string) $model['module'] === 'geo')) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function regions()
    {
        $json = @file_get_contents(dirname(__FILE__) . '/../../style/geo-regions.json');
        if ($json === false) { return false; }
        $tree = json_decode($json, true);
        return is_array($tree) && $tree ? $tree : false;
    }

    public static function selection($tree, $continent, $country, $province)
    {
        if (!is_array($tree) || $continent === '' || !isset($tree[$continent])) { return false; }
        if ($country === '') { return $province === '' ? array($continent) : false; }
        if (!isset($tree[$continent][$country])) { return false; }
        if ($province === '') { return array($continent, $country); }
        return in_array($province, $tree[$continent][$country], true) ? array($continent, $country, $province) : false;
    }
}
