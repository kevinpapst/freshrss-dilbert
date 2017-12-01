<?php

/**
 * Class DilbertExtension
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
        $this->registerTranslates();
    }
    
    /**
     * Inserts the YouTube video iframe into the content of an entry, if the entries link points to a YouTube watch URL.
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
        }

        return $entry;
    }
}
