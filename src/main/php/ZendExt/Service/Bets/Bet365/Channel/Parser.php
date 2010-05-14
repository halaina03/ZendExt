<?php
/**
 * Bets service channels' parser.
 *
 * @category  ZendExt
 * @package   ZendExt_Service_Bets_Bet365
 * @copyright 2010 Monits
 * @license   Copyright (C) 2010. All rights reserved.
 * @version   Release: 1.0.0
 * @link      http://www.zendext.com
 * @since     1.0.0
 */

/**
 * Bets service channels' parser.
 *
 * @category  ZendExt
 * @package   ZendExt_Service_Bets_Bet365
 * @author    Franco Zeoli <fzeoli@monits.com>
 * @copyright 2010 Monits
 * @license   Copyright 2010. All rights reserved.
 * @version   Release: 1.0.0
 * @link      http://www.zendext.com
 * @since     1.0.0
 */
class ZendExt_Service_Bets_Bet365_Channel_Parser
    implements ZendExt_Service_Bets_Bet365_Channel_Parser_Interface
{
    private $_text;
    private $_parsedResult;

    /**
     * Sets the text to be parsed.
     *
     * @param string $text The text.
     *
     * @return void
     */
    public function setInputText($text)
    {
        $this->_text = $text;
    }

    /**
     * Searchs for the match in the parsed results and returns the paybacks.
     *
     * @param string $local   The local team name.
     * @param string $visitor The visitor team name.
     *
     * @return array
     */
    public function getMatchPayback($local, $visitor)
    {

        if (null === $this->_parsedResult) {
            $this->_parsedResult = $this->_parse();
        }

        $key = $local . ' v ' . $visitor;

        if (isset($this->_parsedResult[$key])) {
            return $this->_parsedResult[$key];
        }

        return null;
    }

    /**
     * Parses the text and returns the result.
     *
     * @return array
     */
    private function _parse()
    {
        preg_match_all(
            '/<td class="an3 w" width="238">([^<]+)<\/td>\s*<td [^>]+><img [' .
            '^>]+><img [^>]+><\/td>\s*<td [^>]+>([^<]+)<\/td>\s*<td [^>]+>([' .
            '^<]+)<\/td>\s*<td [^>]+>([^<]+)<\/td>/',
            $this->_text,
            $matches
        );
        $ret = array();

        foreach ($matches[1] as $key => $match) {
            $ret[$match] = array(
                '1' => $matches[2][$key],
                'X' => $matches[3][$key],
                '2' => $matches[4][$key]
            );
        }

        return $ret;
    }

}