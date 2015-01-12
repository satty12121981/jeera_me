<?php
namespace Country\Controller;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;  
use Zend\Session\Container;   
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Adapter\DbTable as AuthAdapter; 
use Country\Model\Country;  
class CountryController extends AbstractActionController
{
    protected $countryTable;   
     
	public function ajaxCountryListAction(){
		$request = $this->getRequest();
		$countries  = $this->getCountryTable()->fetchAll();
		$result = new JsonModel(array( 'countries' => $countries));
		//$viewModel->setTerminal($request->isXmlHttpRequest());
		return $result;
	}
	public function getCountryTable()
    {
        $sm = $this->getServiceLocator();
		return $this->countryTable = (!$this->countryTable)?$this->countryTable = $sm->get('Country\Model\CountryTable'):$this->countryTable;
    }	 
}