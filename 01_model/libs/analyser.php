<?php

/**
 *   Automatic Detection of Information Leakage Vulnerabilities in
 *   Web Applications.
 *
 *   Copyright (C) 2015-2016 Yakup Ates <Yakup.Ates@rub.de>
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

include 'searcher.php';
include 'remover.php';
// https://github.com/giggsey/libphonenumber-for-php
require __DIR__ . '/../../vendor/autoload.php';

// Set maximum execution time to 5 minutes
ini_set('max_execution_time', 300);

class Analyser {
    private $source;
    private $searcher;
    private $remover;

    public function __construct($source) {
        $this->source = $source;

        $this->searcher = new Searcher($this->source);
        $this->remover = new Remover($this->source);
    }


    /**
     * @short: Searches E-Mail addresses
     * @Note: Regex used:
     * (?:[a-z0-9!#$%&'*+\/=?^_`{|}~-]+(?:(\.|\s*\[dot\]\s*)[a-z0-9!#$%&'*+\/=?^
     * _`{|}~-]+)*|"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x0
     * 1-\x09\x0b\x0c\x0e-\x7f])*")
     * (@|\s*\[at\]\s*)
     * (?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(\.|(\s*\[dot\]\s*)))+)([a-z]{2,})\b
     * @return array
     */
    public function find_email($source) {
        /* Generic */
        $top_level_domains  = "com|org|net|int|edu|gov|mil|";
        /* Country */
        $top_level_domains .= "arpa|ac|ad|ae|af|ag|ai|al|am|an|ao|aq|ar|as|at|";
        $top_level_domains .= "au|aw|ax|az|ba|bb|bd|be|bf|bg|bh|bi|bj|bm|bn|bo|";
        $top_level_domains .= "bq|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|";
        $top_level_domains .= "cl|cm|cn|co|cr|cu|cv|cw|cx|cy|cz|de|dj|dk|dm|do|";
        $top_level_domains .= "dz|ec|ee|eg|eh|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|";
        $top_level_domains .= "gb|gd|ge|gf|gg|gh|gi|gl|gm|gn|gp|gq|gr|gs|gt|gu|";
        $top_level_domains .= "gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|im|in|io|iq|ir|";
        $top_level_domains .= "is|it|je|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|";
        $top_level_domains .= "kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|me|";
        $top_level_domains .= "mg|mh|mk|ml|mm|mn|mo|mp|mq|mr|ms|mt|mu|mv|mw|mx|";
        $top_level_domains .= "my|mz|na|nc|ne|nf|ng|ni|nl|no|np|nr|nu|nz|om|pa|";
        $top_level_domains .= "pe|pf|pg|ph|pk|pl|pm|pn|pr|ps|pt|pw|py|qa|re|ro|";
        $top_level_domains .= "rs|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sk|sl|sm|sn|";
        $top_level_domains .= "so|sr|ss|st|su|sv|sx|sy|sz|tc|td|tf|tg|th|tj|tk|";
        $top_level_domains .= "tl|tm|tn|to|tp|tr|tt|tv|tw|tz|ua|ug|us|uy|uz|va|";
        $top_level_domains .= "vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|za|zm|zw";


        preg_match_all($regex, $source, $result);

        $result = array_unique($result[0], SORT_REGULAR);

        return $result;
    }


    /**
     * @short: Detect version number in given string
     * @algorithm: Uses a regex to find version numbers like 1.0.4
     * @return array
     */
    public function getVersionNumber($string, $all=FALSE) {
        $regex = "/(?:(\d+)\.)(?:(\d+)\.)(\*|\d+)*/";

        preg_match($regex, $string, $result);

        if ($all === FALSE) {
            if (!empty($result[0]))
                return $result[0];
            else
                return NULL;
        } else {
            if (!empty($result[0]))
                return $result;
            else
                return NULL;

        }
    }


    /**
     * @short: Searches phone and fax numbers.
     *
     * Note: https://de.wikipedia.org/wiki/Rufnummer#Schreibweisen
     * @return array
     */
    public function find_phoneNumber($source, $nation="DE") {
        /**
         * Delete potential false-positive tags/attributes
         */
        $source = $this->remover->removeNode($source, "script");
        $source = $this->remover->removeNode($source, "path");
        $source = $this->remover->removeNode($source, "polygon");
        $source = $this->remover->removeNode($source, "polyline");
        $source = $this->remover->removeNode($source, "svg");
        $source = $this->remover->removeNode($source, "symbol");
        $source = $this->remover->removeNode($source, "source");
        $source = $this->remover->removeNode($source, "a");

        $source = $this->remover->removeEvents($source);
        $source = $this->remover->removeComments($source);

        $result = array();
        $phoneNumberUtil    = \libphonenumber\PhoneNumberUtil::getInstance();

        $phoneNumberMatcher = $phoneNumberUtil->findNumbers($source, $nation);

        foreach ($phoneNumberMatcher as $phoneNumberMatch) {
            $phoneNumber = $phoneNumberMatch->rawString();

            /**
             * Given phonenumber will probably not contain a '.' _and_ '-' so
             * filter that.
             */
            if ((strpos($phoneNumber, '.') !== false)
                && (strpos($phoneNumber, '-') !== false)) {
                continue;
            }

            /**
             * Potential phone numbers with too many '/' are probably no phone
             * numbers.
             */
            if (substr_count($phoneNumber, '/') > 2) {
                continue;
            }

            /**
             * Phone numbers in general start with 0 or a +. Filter anything
             * else.
             */
            if (($phoneNumber[0] != "0")
                && ($phoneNumber[0] != "+")) {
                continue;
            }

            /**
             * Filter is_numeric() types which are not relevant here
             */
            if ((strpos($phoneNumber, 'b') !== false)
                || (strpos($phoneNumber, 'e') !== false)) {
                continue;
            }

            /* Check if phoneNumber is a date (false-positive) */
            $date = explode('.', $phoneNumber);
            $date = str_replace(' ', '', $date);
            if (!empty($date) && count($date) === 3) {  // it contains 2 dots
                if (($date[0] <= 12) && ($date[1] <= 31)) {
                    if (checkdate($date[0], $date[1], $date[2])) {
                        continue;
                    }
                } else if (($date[1] <= 12) && ($date[0] <= 31)) {
                    if (checkdate($date[1], $date[0], $date[2])) {
                        continue;
                    }
                }
            }
            $result[] = $phoneNumberMatch->rawString();
        }

        return $result;
    }


    /**
     * @short: Searches the source for plugins of the given CMS.
     * @var CMS: Select CMS for the specific plugins.
     * @var file: Specifies the wordfile.
     * @var plugins: Contains the plugins found in source.
     * TODO: Search via XPath - not filtered yet :(.
     * @return array
     */
    public function analyse_plugins($CMS) {
        $file          = NULL;
        $vulnCheckSite = NULL;

        /* We can only look for plugins if we know */
        if (!empty($CMS)) {
            switch ($CMS) {
            case "wordpress":
            case "wp-content":
                $file = "./wordfiles/Plugins/WPPlugins.conf";
                //$vulnCheckSite = "https://wpvulndb.com/search?utf8=TRUE&text=";
                break;
            case "drupal":
                $file = "./wordfiles/Plugins/DrupPlugins.conf";
                break;
            }

            if ($file !== NULL) {
                $plugins        = array();
                $vulnCheck_list = array();
                $result         = array();
                $isVuln         = array();
                $pVal           = array();
                $cnt            = 0;

                $target_attributes = array(
                    "href",
                    "src",
                    "data",
                    "poster",
                    "codebase"
                );

                $lines = file($file, FILE_IGNORE_NEW_LINES);
                $first = $second = $third = 0;
                foreach ($lines as $line) {
                    $nodes = $this->searcher->in_all_caseInsensitive($line);

                    if (!empty($nodes->length)) {
                        foreach ($nodes as $node) {
                            /* Filter attributes */
                            foreach ($node->attributes as $attr) {
                                if (in_array($attr->name, $target_attributes)) {
                                    if (strpos($attr->value, $line) !== FALSE) {
                                        /* Found plugin */
                                        //$plugins[] = $node;
                                        $pVal[]    = $attr->value;
                                        $vuln_file = "./wordfiles/Plugins/WPvulnDB/" . $line . ".conf";

                                        if (file_exists($vuln_file)) {
                                            $known_vulnCount = count(file($vuln_file));
                                            $found_plugin_v  = $this->getVersionNumber($attr->value);

                                            $vuln_plugins = file($file, FILE_IGNORE_NEW_LINES);
                                            foreach ($vuln_plugins as $vuln_line) {
                                                $vuln_version = $this->getVersionNumber($vuln_line);

                                                if ($vuln_version === $found_plugin_v) {
                                                    if ($vuln_version !== NULL) {
                                                        $result['result'][] = TRUE;
                                                        $result['plugin_name'][] = $line;
                                                        $result['version'][] = $vuln_version;
                                                        break 4;
                                                    }
                                                }
                                            }
                                        }
                                        $result['result'][]      = FALSE;
                                        $result['version'][]     = NULL;
                                        $result['plugin_name'][] = $line;

                                        $cnt++;
                                        if ($cnt === 3)
                                            break 3;
                                        else
                                            break 2;
                                    }
                                }
                            }
                        }
                    }
                }
                //$result['node'] = $plugins;
                $result['pVal'] = $pVal;

                return $result;
            } else {
                return NULL;
            }
        } else {
            return NULL;
        }
    }


    /**
     * @short: Detect CMS using the source.
     * @var ret_nodes: TRUE means the function will return the nodes.
     * * FALSE means the function will only return the name of the
     * * CMS.
     * @var filter_nodeName: Defines HTML tags in which the keywords won't take
     * * effect.
     * @var filter_attrName: Defines HTML attributes in which the keywords won't
     * * take effect.
     * @return array
     */
    public function analyse_cms($file="./wordfiles/CMS.conf") {
        $i         = 0;
        $to_filter = FALSE;
        $result    = array();
        $version   = NULL;
        $isVuln    = FALSE;
        $cms_node  = array();

        $filter_nodeName = array(
            "a"    => "a",
            "div"  => "div",
            "i"    => "i",
            "b"    => "b",
            "style"=> "style"
        );

        $filter_attrName = array(
            "href"  => "href",
            "class" => "class"
        );

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            $nodes = $this->searcher->in_all($line);
            if (!empty($nodes->length)) {
                foreach ($nodes as $node) {
                    foreach ($filter_nodeName as $filter_word) {
                        if ($node->nodeName === $filter_word) {
                            $to_filter = TRUE;
                        } else if (($line === "wordpress") &&
                                  ($node->nodeName === "img")) {
                            $to_filter = TRUE;
                        }
                    }

                    if ($to_filter === FALSE) {
                        foreach ($node->attributes as $attr) {
                            foreach ($filter_attrName as $filter_word) {
                                if ($attr->name === $filter_word) {
                                    $to_filter = TRUE;
                                }
                            }
                        }
                    }

                    if ($to_filter === FALSE) {
                        $cms_node = $node;

                        /**
                         * TODO: If already found version number is not the same
                         * as the newly found one then we can't be sure of the
                         * right version number anymore. Build logic for it.
                         */
                        $tmp = $this->getVersionNumber($attr->value, TRUE);

                        if (!empty($tmp))
                            $version = $tmp[0];

                        /* Negative version number? Nope... */
                        if (($tmp[1] < 0) ||
                           ($tmp[2] < 0) ||
                           ($tmp[3] < 0)) {
                            $version = NULL;
                        }

                        /**
                         * TODO: Handling version ranges etc. for specific CMS'
                         * sucks this way. Get a better approach...
                         */
                        if ($version !== NULL) {
                            if (($line === "xt-commerce") ||
                               ($line === "veyton") ||
                               ($line === "xt:Commerce")) {
                                if ($tmp[1] < 4) {
                                    $isVuln = TRUE;
                                } else if ($tmp[1] == 4) {
                                    if ($tmp[2] == 0) {
                                        if ($tmp[3] < 16) {
                                            $isVuln = TRUE;
                                        }
                                    }
                                }
                            } else if (($line === "wp-content") ||
                                      ($line === "wordpress")) {
                                /**
                                 * https://www.intelligentexploit.com/
                                 * view-details.html?id=23287
                                 */
                                if ($tmp[1] < 4) {
                                    $isVuln = TRUE;
                                } else if ($tmp[1] == 4) {
                                    if ($tmp[2] < 4) {
                                        $isVuln = TRUE;
                                    } else if ($tmp[2] == 4) {
                                        if ($tmp[3] <= 1) {
                                            $isVuln = TRUE;
                                        }
                                    }
                                }
                            } else if ($line === "drupal") {
                                /**
                                 * https://www.drupal.org/security
                                 */
                                if ($tmp[1] == 7) {
                                    $isVuln = TRUE;
                                }
                            } else if ($line === "joomla") {
                                /**
                                 * https://www.cvedetails.com/
                                 * vulnerability-list/vendor_id-3496/
                                 * product_id-16499/Joomla-Joomla-.html
                                 */
                                if ($tmp[1] <= 3) {
                                    if ($tmp[2] <= 4) {
                                        if ($tmp[3] <= 7) {
                                            $isVuln = TRUE;
                                        }
                                    }
                                }
                            } else if ($line === "vbulletin") {
                                /**
                                 * https://www.cvedetails.com/cve/CVE-2015-7808/
                                 */
                                if ($tmp[1] <= 5) {
                                    if ($tmp[2] <= 1) {
                                        if ($tmp[3] <= 9) {
                                            $isVuln = TRUE;
                                        }
                                    }
                                }
                            } else if ($line === "webspell") {
                                if ($tmp[1] <= 4) {
                                    if ($tmp[2] <= 2) {
                                        $isVuln = TRUE;
                                    }
                                }
                            }
                        } else {
                            $isVuln  = FALSE;
                            $version = FALSE;
                        }

                        /* Only CMS name needed */
                        if ($line === "wp-content") {
                            /**
                             * There are two keywords in the keyword
                             * file. But if we find "wp-content" instead of
                             * "wordpress" the name should be returned as
                             * "wordpress"
                             */
                            $line = "wordpress";
                        } else if (($line === "veyton") or
                                  ($line === "xt:Commerce")) {
                            /**
                             * Same as with "wordpress" ...
                             */
                            $line = "xt-commerce";
                        }

                        $result['cms']     = $line;
                        $result['version'] = $version;
                        $result['isVuln']  = $isVuln;
                        $result['node']    = $cms_node;

                        return $result;
                    } else {
                        $to_filter = FALSE;
                    }
                }
            }
        }
    }


    /**
     * @short: Detect used Javascript libraries.
     * @algorithm: It will search for libraries only in script tags, so that
     * * false positives are reduced drastically.
     *
     * @return array
     */
    public function analyse_JSLib($file="./wordfiles/JSLibs.conf") {
        $j = 0;
        $result_ = $result = $version = $isVuln = $lib = array();
        //$lineCount = count(file($file));
        $to_filter = FALSE;

        /* http://domstorm.skepticfx.com/modules?id=529bbe6e125fac0000000003 */
        /* Vulnerable to Selector XSS with class Attribute ('. XSS_VECTOR') */
        $vuln_jquery = array(
            '2.0.3', '2.0.2', '2.0.1', '2.0.0', '1.10.2', '1.10.1',
            '1.10.0', '1.9.1', '1.9.0', '1.8.3', '1.8.2', '1.8.1', '1.8.0',
            '1.7.2', '1.7.1', '1.7.0', '1.6.4', '1.6.3', '1.6.2', '1.6.1',
            '1.6.0', '1.5.2', '1.5.1', '1.5.0', '1.4.4', '1.4.3', '1.4.2',
            '1.4.1', '1.4.0', '1.3.2', '1.3.1', '1.3.0', '1.2.6', '1.2.3'
        );

        /* https://www.cvedetails.com/vulnerability-list/vendor_id-11858/Netease.html */
        $vuln_netease = array('1.1.2', '1.2.0');

        /**
         * https://www.cvedetails.com/vulnerability-list/vendor_id-7662/
         * Expressionengine.html
         */
        $vuln_expressionengine = array('1.6.6', '1.6.4', '1.2.1');

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            $nodes = $this->searcher->in_all($line);
            if (!empty($nodes->length)) {
                foreach ($nodes as $node) {

                    /* Filter Tags */
                    if ($node->nodeName !== "script") {
                        $to_filter = TRUE;
                    }

                    if ($to_filter === FALSE) {
                        $result[] = $node;

                        /* Filter attributes */
                        foreach ($node->attributes as $attr) {
                            if (strpos($attr->value, $line) !== FALSE) {
                                // Get version of Javascript library
                                $tmp   = $this->getVersionNumber($attr->value);
                                $lib[] = $line;

                                if (!empty($tmp)) {
                                    $version[] = $tmp;
                                    $isVuln[]  = FALSE;
                                    $j++;

                                    if ($line === "jquery") {
                                        foreach ($vuln_jquery as $vuln) {
                                            if ($tmp === $vuln) {
                                                $isVuln[$j-1] = TRUE;
                                                break;
                                            }
                                        }
                                    } else if ($line === "netease") {
                                        foreach ($vuln_netease as $vuln) {
                                            if ($tmp === $vuln) {
                                                $isVuln[$j-1] = TRUE;
                                                break;
                                            }
                                        }
                                    } else if (($line === "sitecatalyst") ||
                                              ($line === "omniture")) {
                                        /**
                                         * https://web.nvd.nist.gov/view/vuln/
                                         * detail?vulnId=CVE-2006-6640
                                         */
                                        $isVuln[$j-1] = TRUE;
                                    } else if ($line === "analytics.js") {
                                        /**
                                         * http://www.theregister.co.uk/2008/11/
                                         * 22/google_analytics_as_security_risk/
                                         */
                                        $isVuln[$j-1] = TRUE;
                                    } else if ($line === "marketo") {
                                        /**
                                         * https://www.cvedetails.com/cve/CVE-20
                                         * 14-8379/
                                         */
                                        $isVuln[$j-1] = TRUE;
                                    } else if ($line === "expressionengine") {
                                        foreach ($vuln_expressionengine as $vuln) {
                                            if ($tmp === $vuln) {
                                                $isVuln[$j-1] = TRUE;
                                                break;
                                            }
                                        }
                                    } else if ($line === "dotnetnuke") {
                                        /**
                                         * https://www.cvedetails.com/
                                         * vulnerability-list/vendor_id-2486/
                                         * Dotnetnuke.html
                                         */
                                        $isVuln[$j-1] = TRUE;
                                    } else if ($line === "ektron") {
                                        /**
                                         * https://www.cvedetails.com/
                                         * vulnerability-list/vendor_id-8415/
                                         * Ektron.html
                                         */
                                        $isVuln[$j-1] = TRUE;
                                    } else if ($line === "disqus") {
                                        /**
                                         * https://blog.sucuri.net/2014/06/
                                         * anatomy-of-a-remote-code-execution-bug
                                         * -on-disqus.html
                                         */
                                        $isVuln[$j-1] = TRUE;
                                    } else if ($line === "prototype") {
                                        /**
                                         * https://www.cvedetails.com/
                                         * vulnerability-list/vendor_id-6541/
                                         * Prototypejs.html
                                         */
                                        $isVuln[$j-1] = TRUE;
                                    } else if ($line === "lightbox") {
                                        /**
                                         * https://www.cvedetails.com/
                                         * vulnerability-list/vendor_id-15110/
                                         * product_id-30739/version_id-178428/
                                         * Lightbox-Photo-Gallery-Project-Lightbox
                                         * -Photo-Gallery-1.0.html
                                         */
                                        $isVuln[$j-1] = TRUE;
                                    } else {
                                        $isVuln[$j-1] = "N/A";
                                    }
                                } else {
                                    $version[] = "N/A";
                                    $isVuln[]  = "N/A";
                                }
                            }
                        }
                    } else {
                        $to_filter = FALSE;
                    }
                }
            }
        }
        $result_['nodes']   = $result;
        $result_['version'] = $version;
        $result_['isVuln']  = $isVuln;
        $result_['lib']     = $lib;

        return $result_;
    }


    /**
     * @short: Detect hidden input fields
     * @algorithm: It is searching for hidden input fields, which seem to be
     * * for: usernames/passwords/emails/carts
     *
     * @note: We don't need to search for forms with autocomplete on _and_ are
     * * intented for passwords/usernames/..., because this will output these
     * * anyways.
     * TODO: Which hidden input fields could be also of interest?
     * @return array
     */
    public function analyse_inputs() {
        $result = array();

        /* No need for a file, just search for hidden input fields */
        $line = "hidden";

        $nodes = $this->searcher->in_input($line);
        if (!empty($nodes->length)) {
            //$result = $nodes;
            foreach ($nodes as $node) {
                foreach ($node->attributes as $child) {
                    if (preg_match("/pass|password|passwort|passwd|pw/i",
                                  $child->value) !== 0) {
                        $result[] = $node;
                    } else if (preg_match("/mail|email|e-mail/i",
                                         $child->value) !== 0) {
                        $result[] = $node;
                    } else if (preg_match("/cart|korb|einkauf/i",
                                         $child->value) !== 0) {
                        $result[] = $node;
                    } else if (preg_match("/user|usr|benutzer/i",
                                         $child->value) !== 0) {
                        $result[] = $node;
                    }

                }
            }
        }

        return $result;
    }


    /**
     * @short: Detect interesting comments
     * TODO: reduce false positives (difficult in comments...)+
     * @return array
     */
    public function analyse_comments($file="./wordfiles/comments.conf") {
        $i           = 0;
        $lineCount   = count(file($file));
        $result      = array();
        $uniq_result = array();
        $to_filter   = FALSE;

        while ($i < $lineCount) {
            ++$i;
            $line = getLine($file, $i);
            $line = preg_replace("/\n/", "", $line);

            $nodes = $this->searcher->in_comment($line);
            if (!empty($nodes->length)) {
                foreach ($nodes as $node) {
                    /* Filter Conditional Comments */
                    if (preg_match("/if\s?(lte|lt|gt|gte|[\|\&\!])?\s?IE/",
                                  $node->nodeValue) !== 0) {
                        $to_filter = TRUE;
                    }

                    if ($to_filter === FALSE) {
                        $uniq_result[] = $node->nodeValue;
                    } else {
                        $to_filter = FALSE;
                    }
                }
            }
        }

        /**
         * Suprisingly there are often duplicate comments, just sort those
         * out...
         */
        $uniq_result = array_unique($uniq_result, SORT_LOCALE_STRING);
        $result      = $uniq_result;

        return $result;
    }


    /**
     * @short: Detect all interesting meta tags.
     * @note: The results are not filtered yet. I did not see any reasons to do
     * * so.
     * @return array
     */
    public function analyse_metas($file="./wordfiles/metas.conf") {
        $i         = 0;
        $lineCount = count(file($file));
        $result    = array();

        while ($i < $lineCount) {
            ++$i;
            $line = getLine($file, $i);
            $line = preg_replace("/\n/", "", $line);

            $nodes = $this->searcher->in_meta($line);
            if (!empty($nodes->length)) {
                $result[] = $nodes;
            }
        }

        return $result;
    }


    /**
     * @short: Find all given paths of this host.
     * @var url: Only looks for paths of this host.
     * @var attributes_search: Holds attributes which are able to contain paths.
     * @algorithm: There is a limited count of options, where paths can be
     * placed. Just look for those, which are defined with $attributes_search.
     * @return array
     */
    public function find_path($url) {
        $result = array();
        $host   = parse_url($url, PHP_URL_HOST);

        $attributes_search = array(
            "href",
            "src",
            "data",
            "poster",
            "codebase"
        );

        foreach ($attributes_search as $line) {
            $nodes = $this->searcher->in_attr($line);

            if (!empty($nodes[0])) {
                foreach ($nodes as $node) {
                    foreach ($node->attributes as $attribute) {
                        if ($attribute->name === $line) {
                            $path      = parse_url($attribute->value, PHP_URL_PATH);
                            $host_attr = parse_url($attribute->value, PHP_URL_HOST);

                            if (($host === $host_attr) || (empty($host_attr))) {
                                if ($path !== "/") {
                                    $result[] = $path;
                                }
                            }
                        }
                    }
                }
            }
        }
        /* Duplicate paths are not relevant. */
        $result = array_unique($result, SORT_LOCALE_STRING);

        return $result;
    }

    /**
     * @short: Searches for git files.
     * TODO: Search for SVN.
     * @return array
     */
    public function find_SVN_GIT($source) {
        $regex = '`(?:(?:ssh|rsync|git|https?|file)://)?[a-z0-9.@:/~]+\\.git/?`i';

        preg_match_all($regex, $source, $result);

        $result = array_unique($result[0], SORT_REGULAR);

        return $result;
    }

    /**
     * @short: Searches SQL Query
     * TODO: Search for other than MySQL.
     * Too many false positives!!
     * @return array
     */
    public function find_SQLQuery($source) {
        /* This Regex will catch most MySQL queries */
        $regex  = "/(SELECT|UPDATE|ALTER|CREATE|DROP|RENAME|TRUNCATE|INSERT|DELETE)";
        $regex .= "?\s[A-Z0-9_-]*(TABLE|DATABASE|SCHEMA|FROM|INTO|SET)\s*[A-Z0-9_\-=]*/i";

        preg_match_all($regex, $source, $result);

        $result = array_unique($result[0], SORT_REGULAR);

        return $result;
    }

    /**
     * @short: Searches Creditcards
     * @credits: Regex from (edited): w3af; Author: Alexander Berezhnoy
     * Test on:
     * https://www.paypalobjects.com/en_US/vhelp/paypalmanager_help/credit_card_numbers.htm
     * TODO: Build up a better regex.
     * @return array
     */
    public function find_CC($source) {
        /* This Regex won't find all types of CCs */
        $regex  = "/(([^\w+]|\s)\d{4}[- ]?(\d{4}[- ]?\d{4}|";
        $regex .= "\d{6})[- ]?(\d{5}|\d{4})([^\w+]|\s))/";

        preg_match_all($regex, $source, $result);

        $result = array_unique($result[0], SORT_REGULAR);

        $tmp = array();
        foreach ($result as $r) {
            $tmp[] = substr($r, 1, -1);
        }

        return $tmp;
    }

    /**
     * @short: Searches for IPs.
     * @algorithm: The regex will match all IPs from 0.0.0.0 to
     * * 255.255.255.255. Before adding the found IPs it will check whether they
     * * are valid.
     * @return array
     */
    public function find_IP($source) {
        $regex  = "/\b(([1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.)(([0-9]|";
        $regex .= "[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.) {2}([0-9]|";
        $regex .= "[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\b/";

        preg_match_all($regex, $source, $result);

        /* Duplicate IPs are not relevant. */
        $result = array_unique($result[0], SORT_REGULAR);

        $IP = array();

        foreach ($result as $ips) {
            /* Are the found IPs really valid? */
            if (filter_var($ips, FILTER_VALIDATE_IP)) {
                $IP[] = $ips;
            }
        }

        return $IP;
    }


    /**
     * @short: Looks for tag independent keywords in the source code.
     * @param to_filter: Controls whether a node should be filtered.
     * @return array
     */
    public function analyse_generic($file="./wordfiles/keywords.conf") {
        $i         = 0;
        $result    = array();
        $lineCount = count(file($file));
        $to_filter = FALSE;

        while ($i < $lineCount) {
            ++$i;
            $line = getLine($file, $i);
            $line = preg_replace("/\n/", "", $line);

            $nodes = $this->searcher->in_all($line);
            if (!empty($nodes->length)) {
                foreach ($nodes as $node) {
                    /* Filter Tags */
                    if ($node->nodeName === "div") {
                        $to_filter = TRUE;
                    } else if ($node->nodeName === "p") {
                        $to_filter = TRUE;
                    }

                    /* Filter attributes */
                    /*foreach ($node->attributes as $attr) {

                      }*/

                    if ($to_filter === FALSE) {
                        $result[]  = $node;
                    } else {
                        $to_filter = FALSE;
                    }
                }
            }
        }

        return $result;
    }


    public function getDOM() {
        return $this->searcher->getDOM();
    }
}

?>
