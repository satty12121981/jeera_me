<?php
namespace City\Controller;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;	
use Zend\View\Model\JsonModel; 
use Zend\Session\Container;   
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Adapter\DbTable as AuthAdapter; 
use City\Model\City;  
class CityController extends AbstractActionController
{
    protected $cityTable;	 
	protected $countryTable;  
    public function ajaxCitiesListAction(){
		$error = array();
		$request   = $this->getRequest();
		$cities = array(); 
		if ($request->isPost()){
			$post = $request->getPost();  
			$country = $post->get('country_id');  			 
			$cities = $this->getCityTable()->selectAllCity($country);
		}
		$result = new JsonModel(array( 'cities' => $cities));		 
		return $result;
	}
	public function getCityTable()
    {
		$sm = $this->getServiceLocator();       
		return $this->cityTable =(!$this->cityTable)? $this->cityTable = $sm->get('City\Model\CityTable'):$this->cityTable; 
    }
	public function getCountryTable()
    {
        $sm = $this->getServiceLocator();
		return $this->countryTable =(!$this->countryTable)?$sm->get('Country\Model\CountryTable'):$this->countryTable; 
    } 
}