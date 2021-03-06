<?php
/**
 *     GLFramework, small web application framework.
 *     Copyright (C) 2016.  Manuel Muñoz Rosa
 *
 *     This program is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 3 of the License, or
 *     (at your option) any later version.
 *
 *     This program is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *     GNU General Public License for more details.
 *
 *     You should have received a copy of the GNU General Public License
 *     along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Created by PhpStorm.
 * User: manus
 * Date: 1/03/16
 * Time: 10:23
 */

namespace GLFramework\DaMa\Manipulators;

/**
 * Class CSVManipulator
 *
 * @package GLFramework\DaMa\Manipulators
 */
class CSVManipulator extends ManipulatorCore
{

    private $handle;
    private $separator = ';';

    /**
     * TODO
     *
     * @param $file
     * @param array $config
     */
    public function open($file, $config = array())
    {
        $this->separator = $this->detectSeparator($file);
        $this->handle = fopen($file, 'rb');
    }

    /**
     * TODO
     *
     * @return array
     */
    public function next()
    {
        return fgetcsv($this->handle, null, $this->separator, '\'');
    }

    /**
     * TODO
     *
     * @param $file
     * @return mixed
     */
    private function detectSeparator($file)
    {
        $matches = array(';', ',', '|', '\t');
        $handle = fopen($file, 'rb');
        if ($handle) {
            $minSeparator = $matches[0];
            if (($line = fgets($handle)) !== false) {
                $minOffset = strlen($line);
                foreach ($matches as $item) {
                    $i = strpos($line, $item);
                    if ($i !== false && $i < $minOffset) {
                        $minOffset = $i;
                        $minSeparator = $item;
                    }
                }
            }

            fclose($handle);
            return $minSeparator;
        }
        return $matches[0];
    }
}
