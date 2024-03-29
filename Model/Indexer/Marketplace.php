<?php

/**
 * Codisto Marketplace Sync Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @package   Codisto_Connect
 * @copyright 2016-2022 On Technology Pty. Ltd. (https://codisto.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link      https://codisto.com/
 */

namespace Codisto\Connect\Model\Indexer;

class Marketplace implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    private $codistoHelper;

    public function __construct(
        \Codisto\Connect\Helper\Data $codistoHelper
    ) {
        $this->codistoHelper = $codistoHelper;
    }

    public function execute($ids)
    {
        $ids;
    }

    public function executeFull()
    {
        $merchants = $this->codistoHelper->syncAllMerchants();
        if (!empty($merchants)) {
            $this->codistoHelper->signal($merchants, 'action=sync');
        }
    }

    public function executeList(array $ids)
    {
        $ids;
    }

    public function executeRow($id)
    {
        $id;
    }
}
