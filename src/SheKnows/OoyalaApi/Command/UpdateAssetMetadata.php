<?php

namespace SheKnows\OoyalaApi\Command;

use Guzzle\Service\Command\AbstractCommand;

/**
 * Updates an asset with specified metadata.
 *
 * @guzzle assetId type="string" doc="Asset to update" required="true"
 * @guzzle data type="array" doc="Metadata to add/replace" required="true"
 *
 * @api
 */
class UpdateAssetMetadata extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    protected function build()
    {
        $assetId  = $this->get('assetId');
        $metadata = $this->get('metadata');

        $url = sprintf("assets/%s/metadata", $assetId);

        $headers = array('Content-type' => 'application/json');

        $data = json_encode($metadata);

        $this->request = $this->client->patch($url, $headers, $data);
    }
}