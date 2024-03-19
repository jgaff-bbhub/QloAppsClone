<?php
/**
 * 2010-2023 Webkul.
 *
 * NOTICE OF LICENSE
 *
 * All right is reserved,
 * Please go through LICENSE.txt file inside our module
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please refer to CustomizationPolicy.txt file inside our module for more information.
 *
 * @author Webkul IN
 * @copyright 2010-2023 Webkul IN
 * @license LICENSE.txt
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class statsServiceProducts extends ModuleGrid
{
    private $query = null;
    private $columns = null;
    private $default_sort_column = null;
    private $paging_message = null;

    public function __construct()
    {
        $this->name = 'statsserviceproducts';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.0';
        $this->author = 'Webkul';
        $this->need_instance = 0;

        parent::__construct();

        $this->paging_message = sprintf($this->l('Displaying %1$s of %2$s'), '{0} - {1}', '{2}');

        $this->displayName = $this->l('Extra services overview');
        $this->description = $this->l('Show room extra services overview based on sales.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        return (parent::install() && $this->registerHook('AdminStatsModules'));
    }

    public function hookAdminStatsModules($params)
    {
        $engine_params_services = $this->getServicesParams();
        $engine_params_facilities = $this->getFacilitiesParams();

        if (Tools::getValue('export')) {
            if (Tools::getValue('option') == 'services') {
                $this->csvExport($engine_params_services);
            } else if (Tools::getValue('option') == 'facilities') {
                $this->csvExport($engine_params_facilities);
            }
        }

        $this->context->smarty->assign(array(
            'module_name' => $this->displayName,
            'grid_table_services' => $this->engine($engine_params_services),
            'grid_table_facilities' => $this->engine($engine_params_facilities),
            'export_link_services' => Tools::safeOutput($_SERVER['REQUEST_URI']).'&export=1&option=services',
            'export_link_facilities' => Tools::safeOutput($_SERVER['REQUEST_URI']).'&export=1&option=facilities',
        ));

        return $this->display(__FILE__, 'services_content_block.tpl');
    }

    public function getServicesParams()
    {
        return array(
            'title' => $this->l('Services'),
            'columns' => array(
                array(
                    'id' => 'display_name',
                    'header' => $this->l('Name'),
                    'dataIndex' => 'display_name',
                    'align' => 'left'
                ),
                array(
                    'id' => 'auto_add_to_cart',
                    'header' => $this->l('Auto add to cart'),
                    'dataIndex' => 'auto_add_to_cart',
                    'align' => 'center'
                ),
                array(
                    'id' => 'totalQuantitySold',
                    'header' => $this->l('Quantity sold'),
                    'dataIndex' => 'totalQuantitySold',
                    'align' => 'center'
                ),
                array(
                    'id' => 'avgPriceSold',
                    'header' => $this->l('Average Price'),
                    'dataIndex' => 'avgPriceSold',
                    'align' => 'center'
                ),
                array(
                    'id' => 'totalPriceSold',
                    'header' => $this->l('Sales'),
                    'dataIndex' => 'totalPriceSold',
                    'align' => 'center'
                ),
                array(
                    'id' => 'active',
                    'header' => $this->l('Active'),
                    'dataIndex' => 'active',
                    'align' => 'center'
                )
            ),
            'defaultSortColumn' => 'totalPriceSold',
            'defaultSortDirection' => 'DESC',
            'pagingMessage' => $this->paging_message,
            'option' => 'services'
        );
    }

    public function getFacilitiesParams()
    {
        return array(
            'title' => $this->l('Facilities'),
            'columns' => array(
                array(
                    'id' => 'display_name',
                    'header' => $this->l('Name'),
                    'dataIndex' => 'display_name',
                    'align' => 'left'
                ),
                array(
                    'id' => 'totalQuantitySold',
                    'header' => $this->l('Quantity sold'),
                    'dataIndex' => 'totalQuantitySold',
                    'align' => 'center'
                ),
                array(
                    'id' => 'avgPriceSold',
                    'header' => $this->l('Average Price'),
                    'dataIndex' => 'avgPriceSold',
                    'align' => 'center'
                ),
                array(
                    'id' => 'totalPriceSold',
                    'header' => $this->l('Sales'),
                    'dataIndex' => 'totalPriceSold',
                    'align' => 'center'
                ),
            ),
            'defaultSortColumn' => 'totalPriceSold',
            'defaultSortDirection' => 'DESC',
            'pagingMessage' => $this->paging_message,
            'option' => 'facilities'
        );
    }

    public function setOption($option)
    {
		$date_between = $this->getDate();
        switch($option) {
            case 'services' :
                $this->setQueryForServices($date_between);
                break;
            case 'facilities' :
                $this->setQueryForFacilities($date_between);
                break;
        }
    }

    public function setQueryForServices($date_between)
    {
        $this->query = '(SELECT IFNULL(pl.`name`, od.`product_name`) as `display_name`, p.`active`, p.`auto_add_to_cart`,
            ROUND(IFNULL(SUM(spod.`total_price_tax_excl` / o.`conversion_rate`), 0), 2) / SUM(spod.`quantity`) AS avgPriceSold,
            IFNULL(SUM(spod.`quantity`), 0) AS totalQuantitySold,
            ROUND(IFNULL(SUM(spod.`total_price_tax_excl` / o.`conversion_rate`), 0), 2) AS totalPriceSold
            FROM '._DB_PREFIX_.'htl_room_type_service_product_order_detail spod
            LEFT JOIN  '._DB_PREFIX_.'product p
            ON (spod.`id_product` = p.`id_product`)
            LEFT JOIN '._DB_PREFIX_.'product_lang pl ON (p.id_product = pl.id_product AND pl.id_lang = '.(int)$this->getLang().')
            INNER JOIN '._DB_PREFIX_.'orders o ON (spod.id_order = o.id_order)
            INNER JOIN '._DB_PREFIX_.'order_detail od ON (od.id_order = o.id_order)
            INNER JOIN `'._DB_PREFIX_.'htl_booking_detail` hbd ON (spod.`id_htl_booking_detail` = hbd.`id`)
            WHERE o.valid = 1 AND o.invoice_date BETWEEN '.$date_between.'
            '.HotelBranchInformation::addHotelRestriction(false, 'hbd').'
            AND od.`is_booking_product` = 0
            GROUP BY spod.id_product)
            UNION
            (SELECT pl.`name` as `display_name`, p.`active`, p.`auto_add_to_cart`,
            0 AS avgPriceSold,
            0 AS totalQuantitySold,
            0 AS totalPriceSold
            FROM '._DB_PREFIX_.'product p
            LEFT JOIN '._DB_PREFIX_.'product_lang pl
            ON (p.`id_product` = pl.`id_product` AND pl.`id_lang` = '.(int)$this->getLang().')
            WHERE p.`id_product` NOT IN (
                SELECT DISTINCT(spod.`id_product`)
                FROM '._DB_PREFIX_.'htl_room_type_service_product_order_detail spod
                INNER JOIN '._DB_PREFIX_.'orders o ON (spod.id_order = o.id_order)
                INNER JOIN '._DB_PREFIX_.'order_detail od ON (od.id_order = o.id_order)
                INNER JOIN `'._DB_PREFIX_.'htl_booking_detail` hbd ON (spod.`id_htl_booking_detail` = hbd.`id`)
                WHERE o.valid = 1 AND o.invoice_date BETWEEN '.$date_between.'
                '.HotelBranchInformation::addHotelRestriction(false, 'hbd').'
                AND od.`is_booking_product` = 0
            )
            AND p.`booking_product` = 0)';

        $this->_totalCount = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT COUNT(p.`id_product`) FROM '._DB_PREFIX_.'product p WHERE p.`booking_product` = 0 AND p.`active` = 1'
        );

        $this->option = 'services';
    }

    public function setQueryForFacilities($date_between)
    {
        $this->query = '(SELECT bd.`name` as `display_name`,
            ROUND(IFNULL(SUM(bd.`total_price_tax_excl` / o.`conversion_rate`), 0), 2) / COUNT(bd.`id_booking_demand`) as avgPriceSold,
            IFNULL(COUNT(bd.`id_booking_demand`), 0) AS totalQuantitySold,
            ROUND(IFNULL(SUM(bd.`total_price_tax_excl` / o.`conversion_rate`), 0), 2) AS totalPriceSold
            FROM '._DB_PREFIX_.'htl_booking_demands bd
            INNER JOIN `'._DB_PREFIX_.'htl_booking_detail` hbd ON (bd.`id_htl_booking` = hbd.`id`)
            INNER JOIN '._DB_PREFIX_.'orders o ON (hbd.id_order = o.id_order)
            WHERE o.valid = 1 AND o.invoice_date BETWEEN '.$date_between.'
            '.HotelBranchInformation::addHotelRestriction(false, 'hbd').'
            GROUP BY bd.`name`)
            UNION
            (SELECT IFNULL(gdaol.`name`, gdl.`name`) as `display_name`,
            0 as avgPriceSold,
            0 AS totalQuantitySold,
            0 AS totalPriceSold
            FROM '._DB_PREFIX_.'htl_room_type_global_demand gd
            LEFT JOIN '._DB_PREFIX_.'htl_room_type_global_demand_lang gdl
            ON (gd.id_global_demand = gdl.id_global_demand AND gdl.id_lang = '.(int)$this->getLang().')
            LEFT JOIN `'._DB_PREFIX_.'htl_room_type_global_demand_advance_option` gdao
            ON (gd.id_global_demand = gdao.id_global_demand)
            LEFT JOIN `'._DB_PREFIX_.'htl_room_type_global_demand_advance_option_lang` gdaol
            ON (gdao.id_option = gdaol.id_option AND gdaol.id_lang = '.(int)$this->getLang().')
            WHERE 1
            GROUP BY gdaol.`name`, gdl.`name`
            HAVING `display_name` NOT IN (SELECT bd.`name`  FROM '._DB_PREFIX_.'htl_booking_demands bd
            INNER JOIN `'._DB_PREFIX_.'htl_booking_detail` hbd ON (bd.`id_htl_booking` = hbd.`id`)
            INNER JOIN '._DB_PREFIX_.'orders o ON (hbd.id_order = o.id_order)
            WHERE o.valid = 1 AND o.invoice_date BETWEEN '.$date_between.'
            '.HotelBranchInformation::addHotelRestriction(false, 'hbd').' ))';

        $this->_totalCount = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT COUNT(`id_global_demand`) FROM '._DB_PREFIX_.'htl_room_type_global_demand'
        );

        $this->option = 'facilities';
    }

    public function getData()
    {
        $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
        if (Validate::IsName($this->_sort))
		{
			$this->query .= ' ORDER BY `'.bqSQL($this->_sort).'`';
			if (isset($this->_direction) && Validate::isSortDirection($this->_direction))
				$this->query .= ' '.$this->_direction;
		}

        if (($this->_start === 0 || Validate::IsUnsignedInt($this->_start)) && Validate::IsUnsignedInt($this->_limit))
			$this->query .= ' LIMIT '.(int)$this->_start.', '.(int)$this->_limit;

		$values = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->query);
        foreach ($values as &$value)
        {
            if (!Tools::getValue('export')) {
                if ('services' == $this->option) {
                    if (is_null($value['active'])) {
                        $value['active'] = '<span class="badge badge-warning">'.$this->l('Deleted').'</span>';
                    } else if (!$value['active']) {
                        $value['active'] = '<span class="badge badge-danger">'.$this->l('No').'</span>';
                    } else {
                        $value['active'] = '<span class="badge badge-success">'.$this->l('Yes').'</span>';
                    }
                    if (is_null($value['auto_add_to_cart'])) {
                        $value['auto_add_to_cart'] = '<span class="badge badge-warning">'.$this->l('Deleted').'</span>';
                    } else if (!$value['auto_add_to_cart']) {
                        $value['auto_add_to_cart'] = '<span class="badge badge-danger">'.$this->l('No').'</span>';
                    } else {

                        $value['auto_add_to_cart'] = '<span class="badge badge-success">'.$this->l('Yes').'</span>';
                    }
                }
            	$value['avgPriceSold'] = Tools::displayPrice($value['avgPriceSold'], $currency);
            	$value['totalPriceSold'] = Tools::displayPrice($value['totalPriceSold'], $currency);
            }
            unset($value);
        }

		$this->_values = $values;
    }
}
