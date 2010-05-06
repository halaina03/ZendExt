<?php
/**
 * DataFactory fixture parser.
 *
 * @category  ZendExt
 * @package   ZendExt_Service_DataFactory
 * @copyright 2010 Monits
 * @license   Copyright (C) 2010. All rights reserved.
 * @version   Release: 1.0.0
 * @link      http://www.zendext.com/
 * @since     1.0.0
 */

/**
 * DataFactory fixture parser.
 *
 * @category  ZendExt
 * @package   ZendExt_Service_DataFactory
 * @author    jpcivile <jpcivile@monits.com>
 * @copyright 2010 Monits
 * @license   Copyright 2010. All rights reserved.
 * @version   Release: 1.0.0
 * @link      http://www.zendext.com/
 * @since     1.0.0
 */
class ZendExt_Service_DataFactory_Fixture
{

    const LOCAL = 'local';

    const VISITOR = 'visitante';

    private $_matches;

    /**
     * Instance a new Fixture with the data parsed from the XML
     *
     * @param string $xml The XML to parse.
     */
    public function __construct($xml)
    {

        $this->_matches = $this->_parseFixture(DOMDocument::loadXML($xml));
    }

    /**
     * Parse a fixture.
     *
     * @param DOMDocument $doc The XML document DOM
     *
     * @return array
     */
    private function _parseFixture(DOMDocument $doc)
    {
        $result = array();
        $dates = $doc->getElementsByTagName('fecha');

        foreach ($dates as $date) {

            $group = $date->getAttribute('nombre');
            $roundNumber = $date->getAttribute('nivel');

            $matches = $date->getElementsByTagName('partido');
            foreach ($matches as $match) {

                $matchDate = $match->getAttribute('fecha');
                $matchTime = $match->getAttribute('hora');

                $timestamp = new Zend_Date($matchDate, ZendExt_Service_DataFactory::DATE_FORMAT);
                $timestamp->setTime($matchTime, ZendExt_Service_DataFactory::TIME_FORMAT);

                $state = $match->getElementsByTagName('estado')->item(0)->getAttribute('id');

                $stadium = $match->getAttribute('nombreEstadio');

                $local = $this->_getTeamData($match, self::LOCAL);
                $visitor = $this->_getTeamData($match, self::VISITOR);
                $data = array(
                            'group' => $group,
                            'roundNumber' => $roundNumber,
                            'timestamp' => $timestamp->getTimestamp(),
                            'state' => $state,
                            'local' => $local,
                            'visitor' => $visitor,
                            'stadium' => $stadium
                        );

                $result[] = new ZendExt_Service_DataFactory_Match($data);
            }
        }

        return $result;
    }

    /**
     * Retrieve team name, goals and penalty goals, for either local or visitor team.
     *
     * @param DOMElement $match The node that has the data.
     * @param string     $team  Either 'local' or 'visitante'
     *
     * @return array
     */
    private function _getTeamData(DOMElement $match, $team)
    {
        $name = $match->getElementsByTagName($team)->item(0)->getAttribute('pais');
        $goals = $match->getElementsByTagName('goles'.$team)->item(0)->nodeValue;
        $penaltyGoals = $match->getElementsByTagName('golesDefPenales'.$team)->item(0)->nodeValue;

        return array(
                   'name' => $name,
                   'goals' => $goals,
                   'penaltyGoals' => $penaltyGoals
               );
    }

    /**
     * Get an array of ZendExt_DataFactory_Service_Match.
     *
     * @return array
     */
    public function getMatchData()
    {
        return $this->_matches;
    }
}
