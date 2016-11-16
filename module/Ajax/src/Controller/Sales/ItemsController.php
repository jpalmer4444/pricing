<?php

namespace Ajax\Controller\Sales;

use DataAccess\FFM\Entity\ItemPriceOverride;
use DateTime;
use Doctrine\DBAL\LockMode;
use Exception;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

/**
 * Description of ItemsController
 *
 * @author jasonpalmer
 */
class ItemsController extends AbstractRestfulController {

    protected $restService;
    protected $logger;
    protected $myauthstorage;
    protected $pricingconfig;
    protected $entityManager;
    protected $itemsFilterService;
    protected $checkboxService;

    public function __construct($container) {
        $this->restService = $container->get('RestService');
        $this->checkboxService = $container->get('CheckboxService');
        $this->logger = $container->get('LoggingService');
        $this->myauthstorage = $container->get('Login\Model\MyAuthStorage');
        $this->pricingconfig = $container->get('config')['pricing_config'];
        $this->entityManager = $container->get('FFMEntityManager')->getEntityManager();
        $this->itemsFilterService = $container->get('ItemsFilterTableArrayService');
    }

    public function getList() {
        switch ($this->params()->fromQuery("action")) {
            case "overridePrice" : {
                    return $this->overridePrice();
                }
            case "removeRow" : {
                    return $this->removeRow();
                }
            case "select" : {
                    return $this->select();
                }
            case "unselect" : {
                    return $this->unselect();
                }
            case "customerlisttableget" :
            default : {
                    return $this->getTable();
                }
        }
    }

    public function rest($url, $method = "GET", $params = []) {
        return $this->restService->rest($url, $method, $params);
    }

    protected function overridePrice() {
        $customerid = $this->params()->fromQuery('customerid');
        $rowIndex = $this->params()->fromQuery('index');
        $overrideprice = $this->params()->fromQuery('overrideprice');
        $sku = $this->params()->fromQuery('sku');
        $comment = $this->params()->fromQuery('comment');
        $this->logger->info('Saving overrideprice: ' . $overrideprice . '.');

        //save overridePrice in DB
        $record = new ItemPriceOverride();
        $created = new DateTime("now");
        $record->setCreated($created);
        $record->setActive(true);
        $record->setCustomerid($customerid);
        $record->setSku($sku);
        if (!empty($overrideprice)) {
            $int2 = filter_var($overrideprice, FILTER_SANITIZE_NUMBER_INT);
            $record->setOverrideprice($int2);
        }
        if (!empty($comment)) {
            $record->setComment($comment);
        }
        $this->entityManager->getConnection()->beginTransaction(); // suspend auto-commit
        try {
            //... do some work
            $salesperson = $this->entityManager->find('DataAccess\FFM\Entity\User', 
                    empty($this->myauthstorage->getSalespersonInPlay()) ? 
                    $this->myauthstorage->getUser()->getUsername() : 
                $this->myauthstorage->getSalespersonInPlay()->getUsername(), 
                    LockMode::PESSIMISTIC_READ);

            $record->setSalesperson($salesperson);
            $this->entityManager->persist($record);
            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
            return new JsonModel(array(
                'success' => true,
                'index' => $rowIndex,
                'overrideprice' => number_format($record->getOverrideprice() / 100, 2),
                'comment' => $record->getComment(),
            ));
        } catch (Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            throw $e;
        }
        return new JsonModel(array(
                'success' => false,
            ));
    }

    protected function getTable() {
        $this->logger->info('Retrieving ' . $this->pricingconfig['by_sku_object_items_controller'] . '.');
        $customerid = $this->params()->fromQuery('customerid');
        $params = $this->getBaseBySkuParams();
        $params["customerid"] = $customerid;
        $method = $this->pricingconfig['by_sku_method'];
        $json = $this->rest($this->pricingconfig['by_sku_base_url'], $method, $params);
        $restcallitemsmerged = [];
        if ($json && array_key_exists($this->pricingconfig['by_sku_object_items_controller'], $json)) {
            $restcallitemsmerged = $this->itemsFilterService->_filter($json[$this->pricingconfig['by_sku_object_items_controller']], $customerid);
        } else {
            $this->logger->debug('No ' . $this->pricingconfig['by_sku_object_items_controller'] . ' items found.');
        }

        return new JsonModel(array(
            "data" => $restcallitemsmerged
        ));
    }

    protected function removeRow() {
        $sku = $this->params()->fromQuery('sku');
        $customerid = $this->params()->fromQuery('customerid');
        $this->logger->info('Removing SKU ' . $sku . ' and adding to session removedSKUS.');
        $userinplay = $this->myauthstorage->getSalespersonInPlay();
        if(empty($userinplay)){
            $userinplay = $this->myauthstorage->getUser();
        }
        if(is_array($sku)){
            foreach($sku as $s){
                $this->checkboxService->removeRemovedSKU($s, $customerid, $userinplay->getUsername());
            }
        }else{
            $this->checkboxService->removeRemovedSKU($sku, $customerid, $userinplay->getUsername());
        }
        return new JsonModel(array(
            "success" => $sku
        ));
    }

    protected function select() {
        $skus = $this->params()->fromQuery('skus');
        $customerid = $this->params()->fromQuery('customerid');
        //$this->logger->info('Selecting ' . $skus);
        $userinplay = $this->myauthstorage->getSalespersonInPlay();
        if(empty($userinplay)){
            $userinplay = $this->myauthstorage->getUser();
        }
        foreach($skus as $sku){
            $this->checkboxService->addRemovedSKU($sku, $customerid, $userinplay->getUsername());
        }
        return new JsonModel(array(
            "success" => true
        ));
    }

    protected function unselect() {
        $skus = $this->params()->fromQuery('skus');
        if (empty($skus)) {
            return $this->unselectAll();
        }
        $customerid = $this->params()->fromQuery('customerid');
        //$this->logger->info('Unselecting ' . $skus);
        $userinplay = $this->myauthstorage->getSalespersonInPlay();
        if(empty($userinplay)){
            $userinplay = $this->myauthstorage->getUser();
        }
        foreach($skus as $sku){
            $this->checkboxService->removeRemovedSKU($sku, $customerid, $userinplay->getUsername());
        }
        return new JsonModel(array(
            "success" => true
        ));
    }

    protected function unselectAll() {
        $customerid = $this->params()->fromQuery('customerid');
        $this->logger->info('UnSelecting All.');
        $userinplay = $this->myauthstorage->getSalespersonInPlay();
        if(empty($userinplay)){
            $userinplay = $this->myauthstorage->getUser();
        }
        $removedSKUS = $this->checkboxService->getRemovedSKUS($customerid, $userinplay->getUsername());
        foreach ($removedSKUS as $sku) {
            $this->checkboxService->removeRemovedSKU($sku, $customerid, $userinplay->getUsername());
        }
        return new JsonModel(array(
            "success" => true
        ));
    }

    private function getBaseBySkuParams() {
        return [
            "id" => $this->pricingconfig['by_sku_userid'],
            "pw" => $this->pricingconfig['by_sku_password'],
            "object" => $this->pricingconfig['by_sku_object_items_controller']
        ];
    }

}
