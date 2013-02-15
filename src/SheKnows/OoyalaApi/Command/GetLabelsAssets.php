<?php

namespace SheKnows\OoyalaApi\Command;

use Guzzle\Service\Command\AbstractCommand;

/**
 * Retrieve all assets by a label.
 *
 * @guzzle labelId type="string" doc="Base label to retrieve assets for" required="true"
 * @guzzle include type="string" doc="Retrieve metadata/labels as well" required="false"
 * @guzzle includeChildren type="boolean" doc="Retrieve label's children as well" required="false"
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

        $url = sprintf("labels/%s/assets", $labelId);

        $headers = array('Content-type' => 'application/json');

        $data = json_encode($metadata);

        $this->request = $this->client->patch($url, $headers, $data);
    }

    /**
     * {@inheritDoc}
     */
    protected function process()
    {
        // Execute as normally if includeChildren is false
        if (!$this->get('includeChildren') || null === $this->get('includeChildren')) {
            return parent::process();
        }

        // Loop through all of a label's children and retrieve any child assets
        var_dump($this->getResult());
        exit;

        $this->result;
    }

    /**
     * Self referenced function to make it easy to retrieve children is needed
     */
    private function getAssetsByLabelId($labelId)
    {
        return $this->getClient()->getCommand('GetLabelsAssets', arrya(
            'labelId' => $labelId,
            'include' => $this->get('include'),
            'includeChildren' => $this->get('includeChildren')
        ))->execute();
    }
}