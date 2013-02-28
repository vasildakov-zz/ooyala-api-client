<?php

namespace SheKnows\OoyalaApi\Command;

use Guzzle\Service\Command\AbstractCommand;

/**
 * Retrieve all assets by a label.
 *
 * @guzzle labelId type="string" doc="Base label to retrieve assets for" required="true"
 * @guzzle include type="string" doc="Retrieve metadata/labels as well" required="false"
 * @guzzle includeChildren type="boolean" doc="Retrieve label's children as well" required="false"
 *
 * @api
 */
class GetLabelsAssets extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    protected function build()
    {
        $labelId = $this->get('labelId');
        $include = $this->get('include');

        // Need to have label information available in assets if requesting to includeChildren
        if ($this->get('includeChildren') && !strstr($include, 'labels')) {
            if (null === $include) {
                $this->set('include', 'labels');
            } else {
                $this->set('include', $include . ',labels');
            }
        }

        $url = sprintf("labels/%s/assets", $labelId);
        if (null !== $this->get('include')) {
            $url .= '?include=' . $this->get('include');
        }

        $headers = array('Content-type' => 'application/json');

        $this->request = $this->client->get($url, $headers);
    }

    /**
     * {@inheritDoc}
     */
    protected function process()
    {
        parent::process();

        // Execute as normally if includeChildren is false
        if (!$this->get('includeChildren') || null === $this->get('includeChildren')) {
            return;
        }

        $results = $this->getResult();
        $labelId = $this->get('labelId');

        $labelsChildren = $this->getClient()->getCommand('GetLabelsChildren', array(
            'labelId' => $labelId
        ))->execute();

        if (count($labelsChildren['items']) > 0) {
            // Loop through all of a label's children and retrieve any child assets
            foreach($labelsChildren['items'] as $childKey => $childLabel) {
                $childAssets = $this->getAssetsByLabelId($childLabel['id']);
                $results[$childLabel['id']]['next_page'] = $childAssets['next_page'];
                $results[$childLabel['id']]['items']     = $childAssets['items'];
            }
        }

        $this->setResult($results);
    }

    /**
     * Self referenced function to make it easy to retrieve children as needed.
     */
    private function getAssetsByLabelId($labelId)
    {
        return $this->getClient()->getCommand('GetLabelsAssets', array(
            'labelId' => $labelId,
            'include' => $this->get('include'),
            'includeChildren' => $this->get('includeChildren')
        ))->execute();
    }
}