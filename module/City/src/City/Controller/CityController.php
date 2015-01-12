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
	public function citylistAction(){
		$request = $this->getRequest();
		if($this->getRequest()->getMethod() == 'POST') {
			$postedValues = $this->getRequest()->getPost();
			$country_id = $postedValues['country_id'];
			if($country_id!=''){
				$country  = $this->getCountryTable()->getCountry($country_id);  
				if(!empty($country)){ 
					$cities = $this->getCityTable()->selectAllCity($country_id);
					if(!empty($cities)){
					$dataArr[0]['flag'] = "Success";
					$dataArr[0]['cities'] = $cities;            
					echo json_encode($dataArr);
					exit;
					}else{
						$dataArr[0]['flag'] = "Failure";
						$dataArr[0]['message'] = "No more cities are available";
						echo json_encode($dataArr);
					}
				}else{				 
					$dataArr[0]['flag'] = "Failure";
					$dataArr[0]['message'] = "Country not exist in the system";
					echo json_encode($dataArr);
							 
				}
			}else{
				$dataArr[0]['flag'] = "Failure";
				$dataArr[0]['message'] = "Select your country.";
				echo json_encode($dataArr);
				exit;
			}
		} else {
			$dataArr[0]['flag'] = "Failure";
			$dataArr[0]['message'] = "Request not authorised.";
			echo json_encode($dataArr);
			exit;
		}
	}
}