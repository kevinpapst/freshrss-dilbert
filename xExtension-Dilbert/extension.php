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
    public function install()
    {
        return true;
    }

    public function uninstall()
    {
        return true;
    }

    public function handleConfigureAction()
    {
    }

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
        $this->registerHook('entry_before_display', array($this, 'fixDilbert'));
    }

    /**
     * Only here for debugging purpose
     *
     * @param FreshRSS_Entry $entry
     * @return mixed
     */
    public function fixDilbert($entry)
    {
        if (!$this->supports($entry)) {
            return $entry;
        }

        $dom = new DOMDocument;
        $dom->loadHTML($entry->content());
        libxml_use_internal_errors(false);

        $xpath = new DOMXpath($dom);

        $image = $xpath->query("//img");
        if (!is_null($image)) {
            $image = $image->item(0);
            if (!is_null($image)) {
                $source = $image->getAttribute('src');
                if (strpos($source, '//') === 0) {
                    $image->setAttribute('src', 'https:' . $source);
                    $entry->_content($image->ownerDocument->saveHTML($dom));
                }
            }
        }

        return $entry;
    }

    /**
     * Check if we support working on this entry.
     * We do not want to parse every displayed entry, but only the DILBERT ones ;-)
     *
     * @param FreshRSS_Entry $entry
     * @return bool
     */
    protected function supports($entry)
    {
        $link = $entry->link();

        if (
            stripos($link, '://feedproxy.google.com/~r/DilbertDailyStrip') === false &&
            stripos($link, '://feed.dilbert.com/~r/dilbert/daily_strip') === false &&
            stripos($link, '://dilbert.com/strip/') === false
        ) {
            return false;
        }
        return true;
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
        if (!$this->supports($entry)) {
            return $entry;
        }

        $urlParts = explode('/', $entry->link());
        $date = $urlParts[count($urlParts)-1];
        $url = 'https://dilbert.com/strip/' . $date;

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

                // the image URL is now started protocol relative, so we need to prepend https, otherwise
                // reading the articles via the API might fail. In a browser you will not see the problem, only
                // when articles are displayed inside an app that does not support these protocol-less URLs.
                $image = $xpath->query("//img", $node);
                if (!is_null($image)) {
                    $image = $image->item(0);
                    if (!is_null($image)) {
                        $source = $image->getAttribute('src');
                        if (strpos($source, '//') === 0) {
                            $image->setAttribute('src', 'https:' . $source);
                        }
                    }
                }

                $iconWithLink = $node->ownerDocument->saveHTML($node);
                $entry->_content($iconWithLink);
            }

            $entry->_hash($originalHash);
        }

        return $entry;
    }
}
