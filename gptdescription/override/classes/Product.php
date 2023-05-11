<?php  
class Product extends ProductCore
{

     /**
     * {@inheritdoc}
     */
    public function update($null_values = false)
    {
        if ($this->is_virtual) {
            $this->product_type = ProductType::TYPE_VIRTUAL;
        }

        Hook::exec('actionProductUpdate', ['id_product' => (int) $this->id, 'product' => $this]);
        Hook::exec('actionProductSave', ['id_product' => (int) $this->id, 'product' => $this]);

        $return = ObjectModel::update($null_values);
        $this->setGroupReduction();
        $this->updateUnitRatio();

        // Sync stock Reference, EAN13, MPN and UPC
        if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && StockAvailable::dependsOnStock($this->id, Context::getContext()->shop->id)) {
            Db::getInstance()->update('stock', [
                'reference' => pSQL($this->reference),
                'ean13' => pSQL($this->ean13),
                'isbn' => pSQL($this->isbn),
                'upc' => pSQL($this->upc),
                'mpn' => pSQL($this->mpn),
            ], 'id_product = ' . (int) $this->id . ' AND id_product_attribute = 0');
        }

        if ($this->getType() == Product::PTYPE_VIRTUAL && $this->active && !Configuration::get('PS_VIRTUAL_PROD_FEATURE_ACTIVE')) {
            Configuration::updateGlobalValue('PS_VIRTUAL_PROD_FEATURE_ACTIVE', '1');
        }

        return $return;
    }
}