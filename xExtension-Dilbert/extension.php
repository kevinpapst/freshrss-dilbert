<?php

/**
 * Class DilbertExtension
 *
 * Due to the nature of ths extension, this significantly slows down the fetching process ... but ONLY for the Dilbert feed itself!
 * You should only recognize it during the initial adding and importing process.
 *
 * Latest version can be found at https://github.com/kevinpapst/freshrss-dilbert
 *
 * @author Kevin Papst
 */
class DilbertExtension extends Minz_Extension
{
    /**
     * Initialize this extension
     */
    public function init()
    {
        // make sure to not run on server without libxml
        if (!extension_loaded('xml')) {
            return;
        }

        $this->registerHook('entry_before_insert', array($this, 'embedDilbert'));
    }
    
    /**
     * Embed the Comic image into the entry, if the feed is from Dilbert AND the image can be found in
     * the origin sites content.
     *
     * @param FreshRSS_Entry $entry
     * @return mixed
     */
    public function embedDilbert($entry)
    {
        $link = $entry->link();

        if (
            stripos($link, '://feedproxy.google.com/~r/DilbertDailyStrip') === false &&
            stripos($link, '://feed.dilbert.com/~r/dilbert/daily_strip') === false
        ) {
            return $entry;
        }

        $urlParts = explode('/', $link);
        $date = $urlParts[count($urlParts)-1];
        $url = 'http://dilbert.com/strip/' . $date;

        libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $dom->loadHTMLFile($url);
        libxml_use_internal_errors(false);
        $xpath = new DOMXpath($dom);

        $comicContainer = $xpath->query("//div[contains(@class, 'comic-item-container')]");

        if (!is_null($comicContainer)) {
            $comicContainer = $comicContainer->item(0);

            $originalHash = $entry->hash();

            // add some meta info
            $entry->_author($comicContainer->getAttribute('data-creator'));
            $entry->_title($entry->title() . ' - ' . $comicContainer->getAttribute('data-title'));

            // Lets not only focus on the image, but keep the link to the original page as well.
            // This is only fair for Scott, who brings us these famous comic strips since so many years!
            $comicLinks = $xpath->query("//a[@class='img-comic-link']", $comicContainer);
            if (!is_null($comicLinks)) {
                $node = $comicLinks->item(0);
                $iconWithLink = $node->ownerDocument->saveHTML($node);
                $entry->_content($iconWithLink);
            }

            $entry->_hash($originalHash);
        }

        return $entry;
    }
}
