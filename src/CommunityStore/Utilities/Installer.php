<?php
namespace Concrete\Package\CommunityStore\Src\CommunityStore\Utilities;

use BlockType;
use BlockTypeSet;
use SinglePage;
use Core;
use Page;
use PageTemplate;
use PageType;
use Group;
use FileSet;
use Config;
use Localization;
use Concrete\Core\Attribute\Key\Category as AttributeKeyCategory;
use Concrete\Core\Attribute\Key\UserKey as UserAttributeKey;
use Concrete\Core\Attribute\Type as AttributeType;
use AttributeSet;
use Concrete\Core\Page\Type\PublishTarget\Type\AllType as PageTypePublishTargetAllType;
use Concrete\Core\Page\Type\PublishTarget\Configuration\AllConfiguration as PageTypePublishTargetAllConfiguration;
use Concrete\Package\CommunityStore\Attribute\Key\StoreOrderKey;
use Concrete\Package\CommunityStore\Src\CommunityStore\Payment\Method as StorePaymentMethod;
use Concrete\Package\CommunityStore\Src\CommunityStore\Shipping\Method\ShippingMethodType as StoreShippingMethodType;
use Concrete\Package\CommunityStore\Src\CommunityStore\Order\OrderStatus\OrderStatus as StoreOrderStatus;
use Concrete\Package\CommunityStore\Src\CommunityStore\Tax\TaxClass as StoreTaxClass;
use Concrete\Package\CommunityStore\Src\Attribute\Key\StoreStoreProductKey;
use Concrete\Core\Attribute\Key\Category;

class Installer
{
    public static function installSinglePages($pkg)
    {
        //install our dashboard single pages
        self::installSinglePage('/dashboard/store', $pkg);
        self::installSinglePage('/dashboard/store/orders/', $pkg);
        self::installSinglePage('/dashboard/store/orders/attributes', $pkg);
        self::installSinglePage('/dashboard/store/products/', $pkg);
        self::installSinglePage('/dashboard/store/discounts/', $pkg);
        self::installSinglePage('/dashboard/store/products/groups', $pkg);
        self::installSinglePage('/dashboard/store/products/categories', $pkg);
        self::installSinglePage('/dashboard/store/products/attributes', $pkg);
        self::installSinglePage('/dashboard/store/settings/', $pkg);
        self::installSinglePage('/dashboard/store/settings/shipping', $pkg);
        self::installSinglePage('/dashboard/store/settings/tax', $pkg);
        self::installSinglePage('/dashboard/store/reports', $pkg);
        self::installSinglePage('/dashboard/store/reports/sales', $pkg);
        self::installSinglePage('/dashboard/store/reports/products', $pkg);
        self::installSinglePage('/cart', $pkg);
        self::installSinglePage('/checkout', $pkg);
        self::installSinglePage('/checkout/complete', $pkg);
        Page::getByPath('/cart/')->setAttribute('exclude_nav', 1);
        Page::getByPath('/checkout/')->setAttribute('exclude_nav', 1);
        Page::getByPath('/checkout/complete')->setAttribute('exclude_nav', 1);
    }

    public static function installSinglePage($path, $pkg)
    {
        $page = Page::getByPath($path);
        if (!is_object($page) || $page->isError()) {
            SinglePage::add($path, $pkg);
        }
    }

    public static function installProductParentPage($pkg)
    {
        $productParentPage = Page::getByPath('/products');
        if (!is_object($productParentPage) || $productParentPage->isError()) {
            $productParentPage = Page::getByID(1)->add(
                PageType::getByHandle('page'),
                [
                    'cName' => t('Products'),
                    'cHandle' => 'products',
                    'pkgID' => $pkg->getPackageID(),
                ]
            );
        }
        $productParentPage->setAttribute('exclude_nav', 1);
    }

    public static function installStoreProductPageType($pkg)
    {
        //install product detail page type
        $pageType = PageType::getByHandle('store_product');
        if (!is_object($pageType)) {
            $template = PageTemplate::getByHandle('full');
            PageType::add(
                [
                    'handle' => 'store_product',
                    'name' => 'Product Page',
                    'defaultTemplate' => $template,
                    'allowedTemplates' => 'C',
                    'templates' => [$template],
                    'ptLaunchInComposer' => 0,
                    'ptIsFrequentlyAdded' => 0,
                ],
                $pkg
            )->setConfiguredPageTypePublishTargetObject(new PageTypePublishTargetAllConfiguration(PageTypePublishTargetAllType::getByHandle('all')));
        }
    }

    public static function setDefaultConfigValues($pkg)
    {
        self::setConfigValue('community_store.productPublishTarget', Page::getByPath('/products')->getCollectionID());
        self::setConfigValue('community_store.symbol', '$');
        self::setConfigValue('community_store.whole', '.');
        self::setConfigValue('community_store.thousand', ',');
        self::setConfigValue('community_store.sizeUnit', 'in');
        self::setConfigValue('community_store.weightUnit', 'lb');
        self::setConfigValue('community_store.taxName', t('Tax'));
        self::setConfigValue('community_store.sizeUnit', 'in');
        self::setConfigValue('community_store.weightUnit', 'lb');
        self::setConfigValue('community_store.guestCheckout', 'always');
    }

    public static function setConfigValue($key, $value)
    {
        $config = Config::get($key);
        if (empty($config)) {
            Config::save($key, $value);
        }
    }

    public static function installPaymentMethods($pkg)
    {
        self::installPaymentMethod('invoice', 'Invoice', $pkg, null, true);
    }

    public static function installPaymentMethod($handle, $name, $pkg = null, $displayName = null, $enabled = true)
    {
        $pm = StorePaymentMethod::getByHandle($handle);
        if (!is_object($pm)) {
            StorePaymentMethod::add($handle, $name, $pkg, $displayName, $enabled);
        }
    }

    public static function installShippingMethods($pkg)
    {
        self::installShippingMethod('flat_rate', 'Flat Rate', $pkg);
        self::installShippingMethod('free_shipping', 'Free Shipping', $pkg);
    }

    public static function installShippingMethod($handle, $name, $pkg)
    {
        $smt = StoreShippingMethodType::getByHandle($handle);
        if (!is_object($smt)) {
            StoreShippingMethodType::add($handle, $name, $pkg);
        }
    }

    public static function installBlocks($pkg)
    {
        $bts = BlockTypeSet::getByHandle('community_store');
        if (!is_object($bts)) {
            BlockTypeSet::add("community_store", "Store", $pkg);
        }
        self::installBlock('community_product_list', $pkg);
        self::installBlock('community_utility_links', $pkg);
        self::installBlock('community_product', $pkg);
    }

    public static function installBlock($handle, $pkg)
    {
        $blockType = BlockType::getByHandle($handle);
        if (!is_object($blockType)) {
            BlockType::installBlockType($handle, $pkg);
        }
    }

    public static function setPageTypeDefaults($pkg)
    {
        $pageType = PageType::getByHandle('store_product');
        $template = $pageType->getPageTypeDefaultPageTemplateObject();
        $pageObj = $pageType->getPageTypePageTemplateDefaultPageObject($template);

        $bt = BlockType::getByHandle('community_product');
        $blocks = $pageObj->getBlocks('Main');
        //only install blocks if there's none on there.
        if (count($blocks) < 1) {
            $data = [
                'productLocation' => 'page',
                'showProductName' => 1,
                'showProductDescription' => 1,
                'showProductDetails' => 1,
                'showProductPrice' => 1,
                'showImage' => 1,
                'showCartButton' => 1,
                'showGroups' => 1,
            ];
            $pageObj->addBlock($bt, 'Main', $data);
        }
    }

    public static function installCustomerGroups($pkg)
    {
        $group = Group::getByName('Store Customer');
        if (!$group || $group->getGroupID() < 1) {
            $group = Group::add('Store Customer', t('Registered Customer in your store'));
        }
    }

    public static function installUserAttributes($pkg)
    {
        //user attributes for customers
        $uakc = AttributeKeyCategory::getByHandle('user');
        $uakc->setAllowAttributeSets(AttributeKeyCategory::ASET_ALLOW_MULTIPLE);

        //define attr group, and the different attribute types we'll use
        $custSet = AttributeSet::getByHandle('customer_info');
        if (!is_object($custSet)) {
            $custSet = $uakc->addSet('customer_info', t('Store Customer Info'), $pkg);
        }
        $text = AttributeType::getByHandle('text');
        $address = AttributeType::getByHandle('address');

        self::installUserAttribute('email', $text, $pkg, $custSet);
        self::installUserAttribute('billing_first_name', $text, $pkg, $custSet);
        self::installUserAttribute('billing_last_name', $text, $pkg, $custSet);
        self::installUserAttribute('billing_address', $address, $pkg, $custSet);
        self::installUserAttribute('billing_phone', $text, $pkg, $custSet);
        self::installUserAttribute('billing_company', $text, $pkg, $custSet);
        self::installUserAttribute('shipping_first_name', $text, $pkg, $custSet);
        self::installUserAttribute('shipping_last_name', $text, $pkg, $custSet);
        self::installUserAttribute('shipping_address', $address, $pkg, $custSet);
        self::installUserAttribute('shipping_company', $text, $pkg, $custSet);
        self::installUserAttribute('vat_number', $text, $pkg, $custSet, [
            'akHandle' => 'vat_number',
            'akName' => t('VAT Number'),
        ]);
    }

    public static function installUserAttribute($handle, $type, $pkg, $set, $data = null)
    {
        $attr = UserAttributeKey::getByHandle($handle);
        if (!is_object($attr)) {
            $name = Core::make("helper/text")->unhandle($handle);
            if (!$data) {
                $data = [
                    'akHandle' => $handle,
                    'akName' => t($name),
                    'akIsSearchable' => false,
                    'uakProfileEdit' => true,
                    'uakProfileEditRequired' => false,
                    'uakRegisterEdit' => false,
                    'akCheckedByDefault' => true,
                ];
            }
            UserAttributeKey::add($type, $data, $pkg)->setAttributeSet($set);
        }
    }

    public static function installOrderAttributes($pkg)
    {
        //create custom attribute category for orders


        $orderCategory = Category::getByHandle('store_order');

        if (!is_object($orderCategory)) {
            $orderCategory = Category::add('store_order', 1, $pkg);
        }

        $orderCategory->associateAttributeKeyType(AttributeType::getByHandle('text'));
        $orderCategory->associateAttributeKeyType(AttributeType::getByHandle('textarea'));
        $orderCategory->associateAttributeKeyType(AttributeType::getByHandle('number'));
        $orderCategory->associateAttributeKeyType(AttributeType::getByHandle('address'));
        $orderCategory->associateAttributeKeyType(AttributeType::getByHandle('boolean'));
        $orderCategory->associateAttributeKeyType(AttributeType::getByHandle('date_time'));

        $orderCustSet = $orderCategory->addSet('order_customer', t('Store Customer Info'), $pkg);
        $orderChoiceSet = $orderCategory->addSet('order_choices', t('Other Customer Choices'), $pkg);


        if (!$orderCustSet) {

            $sets = $orderCategory->getAttributeSets();

            foreach ($sets as $set) {
                if ('order_customer' == $set->getAttributeSetHandle()) {
                    $orderCustSet = $set;
                }
            }
        }

        $text = AttributeType::getByHandle('text');
        $address = AttributeType::getByHandle('address');

        self::installOrderAttribute('email', $text, $pkg, $orderCustSet);
        self::installOrderAttribute('billing_first_name', $text, $pkg, $orderCustSet);
        self::installOrderAttribute('billing_last_name', $text, $pkg, $orderCustSet);
        self::installOrderAttribute('billing_address', $address, $pkg, $orderCustSet);
        self::installOrderAttribute('billing_phone', $text, $pkg, $orderCustSet);
        self::installOrderAttribute('billing_company', $text, $pkg, $orderCustSet);
        self::installOrderAttribute('shipping_first_name', $text, $pkg, $orderCustSet);
        self::installOrderAttribute('shipping_last_name', $text, $pkg, $orderCustSet);
        self::installOrderAttribute('shipping_address', $address, $pkg, $orderCustSet);
        self::installOrderAttribute('shipping_company', $text, $pkg, $orderCustSet);
        self::installOrderAttribute('vat_number', $text, $pkg, $orderCustSet, [
            'akHandle' => 'vat_number',
            'akName' => t('VAT Number'),
        ]);
    }

    public static function installOrderAttribute($handle, $type, $pkg, $set, $data = null)
    {
        $app = \Concrete\Core\Support\Facade\Application::getFacadeApplication();
        $orderCategory = $app->make('Concrete\Package\CommunityStore\Attribute\Category\OrderCategory');

        $attr =  $orderCategory->getByHandle($handle);

        if (!is_object($attr)) {

            $name = Core::make("helper/text")->unhandle($handle);

            $key = new StoreOrderKey();
            $key->setAttributeKeyHandle($handle);
            $key->setAttributeKeyName(t($name));
            $key = $orderCategory->add($type, $key, null, $pkg);
            $key->setAttributeSet($set);
        }
    }

    public static function installProductAttributes($pkg)
    {
        //create custom attribute category for products
        $productCategory = Category::getByHandle('store_product');

        if (!is_object($productCategory)) {
            $productCategory = Category::add('store_product', 1, $pkg);
        }


        $productCategory->associateAttributeKeyType(AttributeType::getByHandle('text'));
        $productCategory->associateAttributeKeyType(AttributeType::getByHandle('textarea'));
        $productCategory->associateAttributeKeyType(AttributeType::getByHandle('number'));
        $productCategory->associateAttributeKeyType(AttributeType::getByHandle('address'));
        $productCategory->associateAttributeKeyType(AttributeType::getByHandle('boolean'));
        $productCategory->associateAttributeKeyType(AttributeType::getByHandle('date_time'));

    }

    public static function addProductSearchIndexTable($pkg)
    {
        $spk = new StoreStoreProductKey();
        $spk->createIndexedSearchTable();
    }

    public static function createDDFileset($pkg)
    {
        //create fileset to place digital downloads
        $fs = FileSet::getByName(t('Digital Downloads'));
        if (!is_object($fs)) {
            FileSet::add(t("Digital Downloads"));
        }
    }

    public static function installOrderStatuses($pkg)
    {
        $table = StoreOrderStatus::getTableName();
        $app = \Concrete\Core\Support\Facade\Application::getFacadeApplication();
        $db = $app->make('database')->connection();
        $statuses = [
            ['osHandle' => 'incomplete', 'osName' => t('Awaiting Processing'), 'osInformSite' => 1, 'osInformCustomer' => 0, 'osIsStartingStatus' => 1],
            ['osHandle' => 'processing', 'osName' => t('Processing'), 'osInformSite' => 1, 'osInformCustomer' => 0, 'osIsStartingStatus' => 0],
            ['osHandle' => 'shipped', 'osName' => t('Shipped'), 'osInformSite' => 1, 'osInformCustomer' => 1, 'osIsStartingStatus' => 0],
            ['osHandle' => 'delivered', 'osName' => t('Delivered'), 'osInformSite' => 1, 'osInformCustomer' => 1, 'osIsStartingStatus' => 0],
            ['osHandle' => 'nodelivery', 'osName' => t('Will not deliver'), 'osInformSite' => 1, 'osInformCustomer' => 1, 'osIsStartingStatus' => 0],
            ['osHandle' => 'returned', 'osName' => t('Returned'), 'osInformSite' => 1, 'osInformCustomer' => 0, 'osIsStartingStatus' => 0],
        ];

        $db->query("DELETE FROM " . $table);

        foreach ($statuses as $status) {
            StoreOrderStatus::add($status['osHandle'], $status['osName'], $status['osInformSite'], $status['osInformCustomer'], $status['osIsStartingStatus']);
        }
    }

    public static function installDefaultTaxClass($pkg)
    {
        $defaultTaxClass = StoreTaxClass::getByHandle("default");
        if (!is_object($defaultTaxClass)) {
            $data = [
                'taxClassName' => t('Default'),
                'taxClassLocked' => true,
            ];
            $defaultTaxClass = StoreTaxClass::add($data);
        }
    }

    public static function upgrade($pkg)
    {
        $path = '/dashboard/store/products/groups';
        $page = Page::getByPath($path);
        if (!is_object($page) || $page->isError()) {
            SinglePage::add($path, $pkg);
        }

        // trigger a reinstall in case new fields have been added
        self::installOrderAttributes($pkg);
        self::installUserAttributes($pkg);

        if (version_compare(\Config::get('concrete.version'), '8.0', '>=')) {
            // skip this for version 8, these items would have already been installed historically
        } else {
            $singlePage = Page::getByPath('/dashboard/store/orders/attributes');
            if ($singlePage->error) {
                self::installSinglePage('/dashboard/store/orders/attributes', $pkg);
            }

            $oakc = AttributeKeyCategory::getByHandle('store_order');
            $orderChoiceSet = $oakc->getAttributeSetByHandle('order_choices');
            if (!($orderChoiceSet instanceof \Concrete\Core\Attribute\Set)) {
                $orderChoiceSet = $oakc->addSet('order_choices', t('Other Customer Choices'), $pkg);
            }

            // now we refresh all blocks
            $items = $pkg->getPackageItems();
            if (is_array($items['block_types'])) {
                foreach ($items['block_types'] as $item) {
                    $item->refresh();
                }
            }
        }
        Localization::clearCache();
        self::installUserAttributes($pkg);
        Installer::addProductSearchIndexTable($pkg);
    }
}
