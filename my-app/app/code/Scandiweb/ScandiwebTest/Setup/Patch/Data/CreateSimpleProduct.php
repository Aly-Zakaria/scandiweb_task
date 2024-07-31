<?php

namespace Scandiweb\ScandiwebTest\Setup\Patch\Data;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\App\State;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Setup\Exception;
use Magento\Store\Model\StoreManagerInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;

class CreateSimpleProduct implements DataPatchInterface
{

    protected ModuleDataSetupInterface $setup;

    protected ProductInterfaceFactory $productInterfaceFactory;

    protected ProductRepositoryInterface $productRepository;

    protected State $appState;

    protected EavSetup $eavSetup;

    protected StoreManagerInterface $storeManager;

    protected SourceItemInterfaceFactory $sourceItemFactory;

    protected SourceItemsSaveInterface $sourceItemsSaveInterface;

    protected CategoryLinkManagementInterface $categoryLink;

    protected CategoryCollectionFactory $categoryCollectionFactory;

    protected array $sourceItems = [];

    public function __construct(
        ModuleDataSetupInterface $setup,
        ProductInterfaceFactory $productInterfaceFactory,
        ProductRepositoryInterface $productRepository,
        State $appState,
        StoreManagerInterface $storeManager,
        EavSetup $eavSetup,
        SourceItemInterfaceFactory $sourceItemFactory,
        SourceItemsSaveInterface $sourceItemsSaveInterface,
        CategoryLinkManagementInterface $categoryLink,
        CategoryCollectionFactory $categoryCollectionFactory,
    ) {
        $this->appState = $appState;
        $this->productInterfaceFactory = $productInterfaceFactory;
        $this->productRepository = $productRepository;
        $this->setup = $setup;
        $this->eavSetup = $eavSetup;
        $this->storeManager = $storeManager;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->sourceItemsSaveInterface = $sourceItemsSaveInterface;
        $this->categoryLink = $categoryLink;
        $this->categoryCollectionFactory = $categoryCollectionFactory;

    }


    public function apply(){
        $this->appState->emulateAreaCode('adminhtml', [$this, 'execute']);

    }

    public function execute(){

        $product = $this->productInterfaceFactory->create();
        try{
            // check if the product already exists
            if ($product->getIdBySku('New_SKU')) {
                return;
            }

            $attributeSetId = $this->eavSetup->getAttributeSetId(Product::ENTITY, 'Default');
            // get the website id from a StoreManagerInterface instance
            $websiteIDs = [$this->storeManager->getStore()->getWebsiteId()];
            $product->setWebsiteIds($websiteIDs);
            // set the default attributes
            $product->setTypeId(Type::TYPE_SIMPLE)
                ->setAttributeSetId($attributeSetId)
                ->setName('Hoodie')
                ->setSku('new-hoodie')
                ->setUrlKey('hoodie')
                ->setPrice(300)
                ->setVisibility(Visibility::VISIBILITY_BOTH)
                ->setStatus(Status::STATUS_ENABLED);

            $product->setStockData(['use_config_manage_stock' => 1, 'is_qty_decimal' => 0, 'is_in_stock' => 1]);


            // save the product to the repository
            $product = $this->productRepository->save($product);

            // set source item...
            $sourceItem = $this->sourceItemFactory->create();
            $sourceItem->setSourceCode('default');
            // set the quantity of items in stock
            $sourceItem->setQuantity(100);
            // add the product's SKU that will be linked to this source item
            $sourceItem->setSku($product->getSku());
            // set the stock status
            $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);
            $this->sourceItems[] = $sourceItem;

            // save the source item
            $this->sourceItemsSaveInterface->execute($this->sourceItems);

            // Assign product to categories (replace with your category IDs)
            $categoryTitles = ['Men', 'Women'];
            $categoryIds = $this->categoryCollectionFactory->create()
                ->addAttributeToFilter('name', ['in' => $categoryTitles])
                ->getAllIds();

            $this->categoryLink->assignProductToCategories($product->getSku(), $categoryIds);


        }
        catch ( Exception $error){
            echo $error;
        }

    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        // TODO: Implement getDependencies() method.
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        // TODO: Implement getAliases() method.
        return [];
    }



}
