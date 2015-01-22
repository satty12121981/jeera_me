<?php
namespace City\Model;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Crypt\BlockCipher;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;
class CityTable extends AbstractTableGateway
{
    protected $table = 'y2m_city'; 
    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new City());
        $this->initialize();
    }   
	public function selectCityByCountry($country_id){
		$data = $select = new Select();
        $select->from($this->table);
		$select->where(array('country_id = '.$country_id));
		$statement = $this->adapter->createStatement();
        $select->prepareStatement($this->adapter, $statement);        
		//echo $select->getSqlString();exit;
        $resultSet = new ResultSet();
        $resultSet->initialize($statement->execute());
        return $resultSet->toArray();  
	}	

    public function selectAllCity(){
        $data = $select = new Select();

        $expression = new Expression(
            "GROUP_CONCAT(city_id,'|',name)"
        );

        $select->from($this->table);
        $select->columns(array('city_name'=>$expression));
        $select->join(array('y2m_country'=>'y2m_country'),'y2m_city.country_id = y2m_country.country_id',array('country_id','country_code','country_title'));
        $select->group('y2m_country.country_id');

        $statement = $this->adapter->createStatement();
        $select->prepareStatement($this->adapter, $statement);        
        //echo $select->getSqlString();exit;
        $resultSet = new ResultSet();
        $resultSet->initialize($statement->execute());
        return $resultSet->toArray();  
    } 
}