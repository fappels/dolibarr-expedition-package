<?php
/* Copyright (C) 2021       Francis Appels          <francis.appels@z-application.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    shipmentpackage/class/actions_package.class.php
 * \ingroup shipmentpackage
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

/**
 * Class ActionsShipmentPackage
 */
class ActionsShipmentPackage
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();


	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;


	/**
	 * Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/**
	 * Execute action
	 *
	 * @param	array			$parameters		Array of parameters
	 * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action      	'add', 'update', 'view'
	 * @return	int         					<0 if KO,
	 *                           				=0 if OK but we want to process standard actions too,
	 *                            				>0 if OK and we want to replace standard actions.
	 */
	public function getNomUrl($parameters, &$object, &$action)
	{
		global $db, $langs, $conf, $user;
		$this->resprints = '';
		return 0;
	}

	/**
	 * Overloading the addMoreActionsButtons function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		if (in_array($parameters['currentcontext'], array('expeditioncard'))) {
			/** @var Expedition $object */
			if ($user->rights->shipmentpackage->shipmentpackage->write && $object->statut == Expedition::STATUS_VALIDATED) {
				$href = dol_buildpath('/shipmentpackage/shipmentpackage_card.php', 2);
				// check for draft package for customer
				dol_include_once('/shipmentpackage/class/shipmentpackage.class.php');
				$shipmentpackage = new ShipmentPackage($this->db);
				$result = $shipmentpackage->fetchAll('', '', 0, 0, array('fk_soc'=>(int) $object->socid, 'status'=>ShipmentPackage::STATUS_DRAFT));
				if (is_array($result) && count($result) > 0) {
					foreach ($result as $package) {
						print '<div class="inline-block divButAction"><a class="butAction" href="' . $href . '?origin=shipping&id=' . $package->id . '&originid=' . $object->id . '&fk_soc=' . $object->socid . '&fk_project=' . $object->fk_project . '&action=addto">' . $langs->trans('AddToPackage', $package->ref) . '</a></div>';
					}
				}
				print '<div class="inline-block divButAction"><a class="butAction" href="' . $href . '?origin=shipping&originid=' . $object->id . '&fk_soc=' . $object->socid . '&fk_project=' . $object->fk_project . '&action=create">' . $langs->trans('CreatePackage') . '</a></div>';
			}
		}
		return $error;
	}

	/**
	 * Overloading the setLinkedObjectSourceTargetType function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function setLinkedObjectSourceTargetType($parameters, &$object, &$action, $hookmanager)
	{
		if (in_array($parameters['currentcontext'], array('shipmentpackagecard'))) {
			$this->results = array('targettype' => $object->element);
			return 1;
		} else {
			return 0;
		}
	}

	/**
	 * Overloading the printOriginObjectLine function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function printOriginObjectLine($parameters, &$object, &$action, $hookmanager)
	{
		global $user;

		$result = 0;

		if (in_array($parameters['currentcontext'], array('shipmentpackagecard'))) {
			if ($user->rights->shipmentpackage->shipmentpackage->write) {
				dol_include_once('/shipmentpackage/class/shipmentpackage.class.php');
				$packageLine = new ShipmentPackageLine($this->db);
				$selectedLines = array(0);
				$originLine = $parameters['line'];
				$pattern = '/'.preg_quote(DOL_URL_ROOT, '/').'(.*)/';
				if (preg_match($pattern, dol_buildpath('/shipmentpackage/tpl', 1), $matches)) {
					$packagePath = $matches[1];
				} else {
					$packagePath = '/core/tpl';
				}
				if (!empty($originLine->detail_batch)) {
					foreach ($originLine->detail_batch as $batch) {
						$originLine->qty = $batch->qty;
						$packagedQty = $packageLine->getQtyPackaged($originLine->id, $batch->id);
						if ($packagedQty > 0) {
							$originLine->qty -= $packagedQty;
						}
						if ($packagedQty < 0) {
							$result = $packagedQty;
						} else {
							$originLine->id = $batch->id;
							$originLine->desc = $batch->batch;
							if ($originLine->qty > 0) {
								$selectedLines[] = $originLine->id;
							}
							$object->printOriginLine($originLine, '', '', $packagePath, $selectedLines);
							$result = 1;
						}
					}
				} else {
					$packagedQty = $packageLine->getQtyPackaged($originLine->id);
					if ($packagedQty > 0) {
						$originLine->qty -= $packagedQty;
					}
					if ($packagedQty < 0) {
						$result = $packagedQty;
					} else {
						if ($originLine->qty > 0) {
							$selectedLines[] = $originLine->id;
						}
						$object->printOriginLine($originLine, '', '', $packagePath, $selectedLines);
						$result = 1;
					}
				}
			}
		}
		return $result;
	}

	/**
	 * Overloading the loadDataForCustomReports function : returns data to complete the customreport tool
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function loadDataForCustomReports($parameters, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$langs->load("shipmentpackage@shipmentpackage");

		$this->results = array();

		$head = array();
		$h = 0;

		if ($parameters['tabfamily'] == 'shipmentpackage') {
			$head[$h][0] = dol_buildpath('/module/index.php', 1);
			$head[$h][1] = $langs->trans("Home");
			$head[$h][2] = 'home';
			$h++;

			$this->results['title'] = $langs->trans("ShipmentPackage");
			$this->results['picto'] = 'shipmentpackage@shipmentpackage';
		}

		$head[$h][0] = 'customreports.php?objecttype='.$parameters['objecttype'].(empty($parameters['tabfamily']) ? '' : '&tabfamily='.$parameters['tabfamily']);
		$head[$h][1] = $langs->trans("CustomReports");
		$head[$h][2] = 'customreports';

		$this->results['head'] = $head;

		return 1;
	}



	/**
	 * Overloading the restrictedArea function : check permission on an object
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int 		      			  	<0 if KO,
	 *                          				=0 if OK but we want to process standard actions too,
	 *  	                            		>0 if OK and we want to replace standard actions.
	 */
	public function restrictedArea($parameters, &$action, $hookmanager)
	{
		global $user;

		if ($parameters['features'] == 'shipmentpackage') {
			if ($user->rights->shipmentpackage->shipmentpackage->read) {
				$this->results['result'] = 1;
				return 1;
			} else {
				$this->results['result'] = 0;
				return 1;
			}
		}

		return 0;
	}

	/**
	 * Execute action completeTabsHead
	 *
	 * @param   array           $parameters     Array of parameters
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         'add', 'update', 'view'
	 * @param   Hookmanager     $hookmanager    hookmanager
	 * @return  int                             <0 if KO,
	 *                                          =0 if OK but we want to process standard actions too,
	 *                                          >0 if OK and we want to replace standard actions.
	 */
	public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user;

		if (!isset($parameters['object']->element)) {
			return 0;
		}
		if ($parameters['mode'] == 'remove') {
			// utilisé si on veut faire disparaitre des onglets.
			return 0;
		} elseif ($parameters['mode'] == 'add') {
			$langs->load('shipmentpackage@shipmentpackage');
			// utilisé si on veut ajouter des onglets.
			$counter = count($parameters['head']);
			$element = $parameters['object']->element;
			$id = $parameters['object']->id;
			// verifier le type d'onglet comme member_stats où ça ne doit pas apparaitre
			// if (in_array($element, ['societe', 'member', 'contrat', 'fichinter', 'project', 'propal', 'commande', 'facture', 'order_supplier', 'invoice_supplier'])) {
			if (in_array($element, ['context1', 'context2'])) {
				$datacount = 0;

				$parameters['head'][$counter][0] = dol_buildpath('/shipmentpackage/shipmentpackage_tab.php', 1) . '?id=' . $id . '&amp;module='.$element;
				$parameters['head'][$counter][1] = $langs->trans('ShipmentPackageTab');
				if ($datacount > 0) {
					$parameters['head'][$counter][1] .= '<span class="badge marginleftonlyshort">' . $datacount . '</span>';
				}
				$parameters['head'][$counter][2] = 'packageemails';
				$counter++;
			}
			if ($counter > 0 && (int) DOL_VERSION < 14) {
				$this->results = $parameters['head'];
				// return 1 to replace standard code
				return 1;
			} else {
				// en V14 et + $parameters['head'] est modifiable par référence
				return 0;
			}
		}
	}

	/* Add here any other hooked methods... */
}
