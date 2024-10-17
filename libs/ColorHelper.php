<?php
namespace IPSymconEchoRemote;

trait ColorHelper
{
    protected function nearestColorName( string $searchedHexColor )
    {
        $searchedRGB = $this->HexToRGB(hexdec($searchedHexColor));

        $nearestColorDistance = -1;
        $nearestColorName = "";
        $nearestColorHex = 0;

        $colorNames = $this->colorNames();

        foreach ($colorNames as $name => $hexColor)
        {
            $namedRGB = $this->HexToRGB(hexdec($hexColor));
            $distance = $this->colorDistance($searchedRGB, $namedRGB );
            if ( $distance  < $nearestColorDistance || $nearestColorDistance < 0){
                $nearestColorDistance = $distance;
                $nearestColorName = $name; 
                $nearestColorHex = $hexColor;
            }
        }
        return array('name' => $nearestColorName, 'color' => $nearestColorHex);
    }

    protected function colorDistance( $rgb1, $rgb2)
    {
        $deltaR = $rgb1[0] - $rgb2[0];
        $deltaG = $rgb1[1] - $rgb2[1];
        $deltaB = $rgb1[2] - $rgb2[2];

        $Rmean = 1/2*($rgb1[0] + $rgb2[0]);

        $colorDistance = sqrt( (2+$Rmean/256)* $deltaR**2 +4 * $deltaG**2 + (2+ (255-$Rmean)/256 * $deltaB**2 ) );

        return $colorDistance;
    }


    protected function HexToRGB($value)
    {
        $RGB = [];
        $RGB[0] = (($value >> 16) & 0xFF);
        $RGB[1] = (($value >> 8) & 0xFF);
        $RGB[2] = ($value & 0xFF);
        $this->SendDebug('HexToRGB', 'R: ' . $RGB[0] . ' G: ' . $RGB[1] . ' B: ' . $RGB[2], 0);
        return $RGB;
    }

    protected function RGBToHex($r, $g, $b)
    {
        return ($r << 16) + ($g << 8) + $b;
    }

    protected function HUE2RGB($p, $q, $t)
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }
        return $p;
    }

    protected function HSLToRGB($h, $s, $l)
    {
        if ($s == 0) {
            $r = $l;
            $g = $l;
            $b = $l; // achromatic
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = $this->HUE2RGB($p, $q, $h + 1 / 3);
            $g = $this->HUE2RGB($p, $q, $h);
            $b = $this->HUE2RGB($p, $q, $h - 1 / 3);
        }
        $r = round($r * 255);
        $g = round($g * 255);
        $b = round($b * 255);

        $color = sprintf('#%02x%02x%02x', $red, $green, $blue);
        return $color;
        //return array(round($r * 255), round($g * 255), round($b * 255));
    }

    protected function convertHSL($h, $s, $l, $toHex = true)
    {
        $h /= 360;
        $s /= 100;
        $l /= 100;

        $r = $l;
        $g = $l;
        $b = $l;
        $v = ($l <= 0.5) ? ($l * (1.0 + $s)) : ($l + $s - $l * $s);
        if ($v > 0) {
            $m;
            $sv;
            $sextant;
            $fract;
            $vsf;
            $mid1;
            $mid2;

            $m = $l + $l - $v;
            $sv = ($v - $m) / $v;
            $h *= 6.0;
            $sextant = floor($h);
            $fract = $h - $sextant;
            $vsf = $v * $sv * $fract;
            $mid1 = $m + $vsf;
            $mid2 = $v - $vsf;

            switch ($sextant) {
                    case 0:
                          $r = $v;
                          $g = $mid1;
                          $b = $m;
                          break;
                    case 1:
                          $r = $mid2;
                          $g = $v;
                          $b = $m;
                          break;
                    case 2:
                          $r = $m;
                          $g = $v;
                          $b = $mid1;
                          break;
                    case 3:
                          $r = $m;
                          $g = $mid2;
                          $b = $v;
                          break;
                    case 4:
                          $r = $mid1;
                          $g = $m;
                          $b = $v;
                          break;
                    case 5:
                          $r = $v;
                          $g = $m;
                          $b = $mid2;
                          break;
              }
        }
        $r = round($r * 255, 0);
        $g = round($g * 255, 0);
        $b = round($b * 255, 0);

        if ($toHex) {
            $r = ($r < 15) ? '0' . dechex($r) : dechex($r);
            $g = ($g < 15) ? '0' . dechex($g) : dechex($g);
            $b = ($b < 15) ? '0' . dechex($b) : dechex($b);
            return "$r$g$b";
        } else {
            return "rgb($r, $g, $b)";
        }
    }

    protected function hsv2rgb($hue, $sat, $val)
    {
        $rgb = [0, 0, 0];
        //calc rgb for 100% SV, go +1 for BR-range
        for ($i = 0; $i < 4; $i++) {
            if (abs($hue - $i * 120) < 120) {
                $distance = max(60, abs($hue - $i * 120));
                $rgb[$i % 3] = 1 - (($distance - 60) / 60);
            }
        }
        //desaturate by increasing lower levels
        $max = max($rgb);
        $factor = 255 * ($val / 100);
        for ($i = 0; $i < 3; $i++) {
            //use distance between 0 and max (1) and multiply with value
            $rgb[$i] = round(($rgb[$i] + ($max - $rgb[$i]) * (1 - $sat / 100)) * $factor);
        }
        $rgb['hex'] = sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
        return $rgb;
    }

    protected function RGBtoHSV($R, $G, $B)    // RGB values:    0-255, 0-255, 0-255
    {                                // HSV values:    0-360, 0-100, 0-100
          // Convert the RGB byte-values to percentages
          $R = ($R / 255);
        $G = ($G / 255);
        $B = ($B / 255);

        // Calculate a few basic values, the maximum value of R,G,B, the
        //   minimum value, and the difference of the two (chroma).
        $maxRGB = max($R, $G, $B);
        $minRGB = min($R, $G, $B);
        $chroma = $maxRGB - $minRGB;

        // Value (also called Brightness) is the easiest component to calculate,
        //   and is simply the highest value among the R,G,B components.
        // We multiply by 100 to turn the decimal into a readable percent value.
        $computedV = 100 * $maxRGB;

        // Special case if hueless (equal parts RGB make black, white, or grays)
        // Note that Hue is technically undefined when chroma is zero, as
        //   attempting to calculate it would cause division by zero (see
        //   below), so most applications simply substitute a Hue of zero.
        // Saturation will always be zero in this case, see below for details.
        if ($chroma == 0) {
            return [0, 0, $computedV];
        }

        // Saturation is also simple to compute, and is simply the chroma
        //   over the Value (or Brightness)
        // Again, multiplied by 100 to get a percentage.
        $computedS = 100 * ($chroma / $maxRGB);

        // Calculate Hue component
        // Hue is calculated on the "chromacity plane", which is represented
        //   as a 2D hexagon, divided into six 60-degree sectors. We calculate
        //   the bisecting angle as a value 0 <= x < 6, that represents which
        //   portion of which sector the line falls on.
        if ($R == $minRGB) {
            $h = 3 - (($G - $B) / $chroma);
        } elseif ($B == $minRGB) {
            $h = 1 - (($R - $G) / $chroma);
        } else { // $G == $minRGB
            $h = 5 - (($B - $R) / $chroma);
        }

        // After we have the sector position, we multiply it by the size of
        //   each sector's arc (60 degrees) to obtain the angle in degrees.
        $computedH = 60 * $h;

        return [round($computedH), round($computedS), round($computedV)];
    }

    protected function xyToHEX($x, $y, $bri)
    {

        // Calculate XYZ values
        $z = 1 - $x - $y;
        $Y = $bri / 254; // Brightness coeff.
        if ($y == 0) {
            $X = 0;
            $Z = 0;
        } else {
            $X = ($Y / $y) * $x;
            $Z = ($Y / $y) * $z;
        }

        // Convert to sRGB D65 (official formula on meethue)
        // old formula
        // $r = $X * 3.2406 - $Y * 1.5372 - $Z * 0.4986;
        // $g = - $X * 0.9689 + $Y * 1.8758 + $Z * 0.0415;
        // $b = $X * 0.0557 - $Y * 0.204 + $Z * 1.057;
        // formula 2016
        $r = $X * 1.656492 - $Y * 0.354851 - $Z * 0.255038;
        $g = -$X * 0.707196 + $Y * 1.655397 + $Z * 0.036152;
        $b = $X * 0.051713 - $Y * 0.121364 + $Z * 1.011530;

        // Apply reverse gamma correction
        $r = ($r <= 0.0031308 ? 12.92 * $r : (1.055) * pow($r, (1 / 2.4)) - 0.055);
        $g = ($g <= 0.0031308 ? 12.92 * $g : (1.055) * pow($g, (1 / 2.4)) - 0.055);
        $b = ($b <= 0.0031308 ? 12.92 * $b : (1.055) * pow($b, (1 / 2.4)) - 0.055);

        // Calculate final RGB
        $r = ($r < 0 ? 0 : round($r * 255));
        $g = ($g < 0 ? 0 : round($g * 255));
        $b = ($b < 0 ? 0 : round($b * 255));

        $r = ($r > 255 ? 255 : $r);
        $g = ($g > 255 ? 255 : $g);
        $b = ($b > 255 ? 255 : $b);

        // Create a web RGB string (format #xxxxxx)
        $this->SendDebug('RGB', 'R: ' . $r . ' G: ' . $g . ' B: ' . $b, 0);

        //$RGB = "#".substr("0".dechex($r),-2).substr("0".dechex($g),-2).substr("0".dechex($b),-2);
        $color = sprintf('#%02x%02x%02x', $r, $g, $b);

        return $color;
    }

    protected function RGBToXy($RGB)
    {
        // Get decimal RGB
        $RGB = sprintf('#%02x%02x%02x', $RGB[0], $RGB[1], $RGB[2]);
        $r = hexdec(substr($RGB, 1, 2));
        $g = hexdec(substr($RGB, 3, 2));
        $b = hexdec(substr($RGB, 5, 2));

        // Calculate rgb as coef
        $r = $r / 255;
        $g = $g / 255;
        $b = $b / 255;

        // Apply gamma correction
        $r = ($r > 0.04045 ? pow(($r + 0.055) / 1.055, 2.4) : ($r / 12.92));
        $g = ($g > 0.04045 ? pow(($g + 0.055) / 1.055, 2.4) : ($g / 12.92));
        $b = ($b > 0.04045 ? pow(($b + 0.055) / 1.055, 2.4) : ($b / 12.92));

        // Convert to XYZ (official formula on meethue)
        // old formula
        //$X = $r * 0.649926 + $g * 0.103455 + $b * 0.197109;
        //$Y = $r * 0.234327 + $g * 0.743075 + $b * 0.022598;
        //$Z = $r * 0        + $g * 0.053077 + $b * 1.035763;
        // formula 2016
        $X = $r * 0.664511 + $g * 0.154324 + $b * 0.162028;
        $Y = $r * 0.283881 + $g * 0.668433 + $b * 0.047685;
        $Z = $r * 0.000088 + $g * 0.072310 + $b * 0.986039;

        // Calculate xy and bri
        if (($X + $Y + $Z) == 0) {
            $x = 0;
            $y = 0;
        } else { // round to 4 decimal max (=api max size)
            $x = round($X / ($X + $Y + $Z), 4);
            $y = round($Y / ($X + $Y + $Z), 4);
        }
        $bri = round($Y * 254);
        if ($bri > 254) {
            $bri = 254;
        }

        $cie['x'] = $x;
        $cie['y'] = $y;
        $cie['bri'] = $bri;
        return $cie;
    }

    protected function colorNames()
    {
            $colorNames = [
            'medium_sea_green'=> '#57ffa0',
            'dark_turquoise'=> '#01fbff',
            'sky_blue'=> '#93e0ff',
            'old_lace'=> '#fff7e8',
            'light_salmon'=> '#ffa07a',
            'ghost_white'=> '#f7f7ff',
            'orange_red'=> '#ff4400',
            'lime_green'=> '#40ff40',
            'deep_pink'=> '#ff1491',
            'hot_pink'=> '#ff68b6',
            'sea_green'=> '#52ff9d',
            'dodger_blue'=> '#1e8fff',
            'goldenrod'=> '#ffc227',
            'red'=> '#ff0000',
            'blue'=> '#4100ff',
            'fuchsia'=> '#ff00ff',
            'green_yellow'=> '#afff2d',
            'pale_goldenrod'=> '#fffab7',
            'light_green'=> '#99ff99',
            'light_sea_green'=> '#2ffff5',
            'saddle_brown'=> '#ff7c1f',
            'cornsilk'=> '#fff7db',
            'dark_slate_gray'=> '#91ffff',
            'gainsboro'=> '#ffffff',
            'cadet_blue'=> '#96fbff',
            'medium_blue'=> '#0000ff',
            'wheat'=> '#ffe7ba',
            'indian_red'=> '#ff7272',
            'antique_white'=> '#fff0db',
            'plum'=> '#ffb9ff',
            'papaya_whip'=> '#ffefd6',
            'web_maroon'=> '#ff0000',
            'lavender_blush'=> '#ffeff4',
            'cyan'=> '#00ffff',
            'burlywood'=> '#ffd29c',
            'floral_white'=> '#fff9ef',
            'navajo_white'=> '#ffddad',
            'medium_turquoise'=> '#57fff9',
            'royal_blue'=> '#4876ff',
            'light_goldenrod'=> '#ffffd6',
            'navy_blue'=> '#0000ff',
            'light_sky_blue'=> '#8ad2ff',
            'medium_aquamarine'=> '#7fffd5',
            'orchid'=> '#ff84fd',
            'seashell'=> '#fff4ed',
            'pale_turquoise'=> '#bcffff',
            'yellow_green'=> '#bfff46',
            'brown'=> '#ff3d3e',
            'dark_khaki'=> '#fff891',
            'spring_green'=> '#00ff7f',
            'dark_violet'=> '#b300ff',
            'purple'=> '#ab24ff',
            'turquoise'=> '#48ffed',
            'dim_gray'=> '#ffffff',
            'dark_cyan'=> '#00ffff',
            'tan'=> '#ffddab',
            'pink'=> '#ffbfcc',
            'dark_blue'=> '#0000ff',
            'light_steel_blue'=> '#cae2ff',
            'rebecca_purple'=> '#aa55ff',
            'light_yellow'=> '#ffffe0',
            'aqua'=> '#34feff',
            'yellow'=> '#ffff00',
            'dark_orchid'=> '#bf40ff',
            'light_cyan'=> '#e0ffff',
            'blue_violet'=> '#9b30ff',
            'dark_salmon'=> '#ffa486',
            'web_green'=> '#00ff3d',
            'moccasin'=> '#ffe1b5',
            'forest_green'=> '#3cff3c',
            'gold'=> '#ffd400',
            'lime'=> '#c7ff1e',
            'olive'=> '#fffc4b',
            'medium_orchid'=> '#e066ff',
            'slate_blue'=> '#856fff',
            'dark_green'=> '#00ff00',
            'bisque'=> '#ffe2c4',
            'coral'=> '#ff7e4f',
            'salmon'=> '#ffa07a',
            'powder_blue'=> '#c3f9ff',
            'steel_blue'=> '#60b7ff',
            'lawn_green'=> '#79ff41',
            'firebrick'=> '#ff2f2f',
            'olive_drab'=> '#bfff3f',
            'white_smoke'=> '#ffffff',
            'linen'=> '#fff5eb',
            'alice_blue'=> '#eff7ff',
            'medium_spring_green'=> '#1aff9d',
            'violet'=> '#ff8bff',
            'light_pink'=> '#ffb5c1',
            'dark_magenta'=> '#ff00ff',
            'web_gray'=> '#ffffff',
            'maroon'=> '#ff468d',
            'medium_violet_red'=> '#ff1aab',
            'crimson'=> '#ff2545',
            'tomato'=> '#ff6347',
            'pale_green'=> '#9dff9d',
            'white'=> '#ffffff',
            'lavender'=> '#9f7fff',
            'light_blue'=> '#c1f0ff',
            'mint_cream'=> '#f4fff9',
            'chocolate'=> '#ff8025',
            'dark_red'=> '#ff0000',
            'medium_slate_blue'=> '#8370ff',
            'light_slate_gray'=> '#c6e1ff',
            'magenta'=> '#ff00ff',
            'dark_olive_green'=> '#a1ff6e',
            'medium_purple'=> '#ac82ff',
            'gray'=> '#ffffff',
            'silver'=> '#ffffff',
            'green'=> '#00ff00',
            'chartreuse'=> '#7fff00',
            'sienna'=> '#ff8248',
            'peach_puff'=> '#ffd8ba',
            'midnight_blue'=> '#3939ff',
            'thistle'=> '#ffe2ff',
            'indigo'=> '#9000ff',
            'light_coral'=> '#ff8888',
            'blanched_almond'=> '#ffeacc',
            'web_purple'=> '#ff00ff',
            'slate_gray'=> '#c9e4ff',
            'rosy_brown'=> '#ffc1c1',
            'sandy_brown'=> '#ffaa64',
            'teal'=> '#34feff',
            'misty_rose'=> '#ffe2e0',
            'pale_violet_red'=> '#ff82ac',
            'beige'=> '#ffffe5',
            'dark_orange'=> '#ff8a25',
            'dark_gray'=> '#ffffff',
            'peru'=> '#ffa44f',
            'deep_sky_blue'=> '#38bdff',
            'dark_goldenrod'=> '#ffbb0e',
            'ivory'=> '#ffffef',
            'honeydew'=> '#efffef',
            'dark_slate_blue'=> '#826fff',
            'dark_sea_green'=> '#c1ffc1',
            'light_gray'=> '#ffffff',
            'cornflower'=> '#6b9eff',
            'orange'=> '#ffa600',
            'lemon_chiffon'=> '#fff9cc',
            'azure'=> '#efffff',
            'snow'=> '#fff9f9',
            'aquamarine'=> '#7fffd2',
            'khaki'=> '#fff495',
            'black'=> '#ffffff'
        ];
        return $colorNames;
    }
}

