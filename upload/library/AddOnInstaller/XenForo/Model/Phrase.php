<?php

class AddOnInstaller_XenForo_Model_Phrase extends XFCP_AddOnInstaller_XenForo_Model_Phrase
{
    public function importPhrasesAddOnXml(SimpleXMLElement $xml, $addOnId, $maxExecution = 0, $offset = 0)
    {
        if ($addOnId == 'XenForo')
        {
            return parent::importPhrasesAddOnXml($xml, $addOnId, $maxExecution, $offset);
        }

        $db = $this->_getDb();

        XenForo_Db::beginTransaction($db);

        $startTime = microtime(true);
        
        $phrases = XenForo_Helper_DevelopmentXml::fixPhpBug50670($xml->phrase);
        $existingPhrases = $this->fetchAllKeyed('
            SELECT *
            FROM xf_phrase
            WHERE language_id = ? and addon_id = ?
        ', 'title', array($languageId, $addOnId));
        $titles = array();
        $current = 0;

        if ($offset == 0)
        {
            $phrasesLookup = array();
            foreach ($phrases AS $phrase)
            {
                $title = (string)$phrase['title'];
                $phrasesLookup[$title] = $phrase;
            }

            $deletedPhrases = array();
            foreach ($existingPhrases AS $title => $phrase)
            {
                if (isset($phrasesLookup[$title]))
                {
                    continue;
                }
                $deletedPhrases[] = $title;
            }
            $db->query('
                DELETE FROM
                FROM xf_phrase
                WHERE language_id = ? and addon_id = ? and title IN (' . $this->_getDb()->quote($deletedPhrases) . ')
            ', 'title', array($languageId, $addOnId));
            unset($deletedPhrases);
            unset($phrasesLookup);
        }

        foreach ($phrases AS $phrase)
        {
            $current++;
            if ($current <= $offset)
            {
                continue;
            }
            $titles[] = (string)$phrase['title'];
        }

        if ($maxExecution)
        {
            // take off whatever we've used
            $maxExecution -= microtime(true) - $startTime;
        }

        $return = $this->importPhrasesXml($xml, 0, $addOnId, $existingPhrases, $maxExecution, $offset);

        XenForo_Db::commit($db);

        return $return;
    }

}

// ******************** FOR IDE AUTO COMPLETE ********************
if (false)
{
    class XFCP_AddOnInstaller_XenForo_Model_Phrase extends XenForo_Model_Phrase {}
}
