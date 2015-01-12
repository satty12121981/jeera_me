<?php
namespace Tag\Model;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\Expression as predicate;
class UserTagTable extends AbstractTableGateway
{ 
    protected $table = 'y2m_user_tag';
    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new UserTag());
        $this->initialize();
    } 
	public function fetchAllUsersOfTag($tag_id)
    {
      	$select = new Select;
		$select->from('y2m_user_tag')
    		->join('y2m_tag', 'y2m_tag.tag_id = y2m_user_tag.user_tag_tag_id', array('tag_title'))
			->join('y2m_user', 'y2m_user.user_id = y2m_user_tag.user_tag_user_id', array('user_first_name', 'user_last_name'))
			->where(array('y2m_user_tag.user_tag_tag_id' => $tag_id))
			->order(array('y2m_user_tag.user_tag_id ASC'));	
		
		$statement = $this->adapter->createStatement();
		$select->prepareStatement($this->adapter, $statement);
		
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());	
		return $resultSet;
    }
	public function deleteUserTag($user_tag_id)
    {
        $this->delete(array('user_tag_id' => $user_tag_id));
    }
	public function getAllUserTagsWithCategiry($user_id){
		$select = new Select;
		$select->from('y2m_user_tag')
    		->join('y2m_tag', 'y2m_tag.tag_id = y2m_user_tag.user_tag_tag_id',  array('tags'=>new Expression('GROUP_CONCAT(y2m_tag.tag_title SEPARATOR \' . \')')))
			->join('y2m_tag_category', 'y2m_tag_category.tag_category_id = y2m_tag.category_id', array('tag_category_title', 'tag_category_icon'))
			->where(array('y2m_user_tag.user_tag_user_id' => $user_id))
			->where(array('y2m_tag_category.tag_category_status' => 1))			 
			->order(array('y2m_tag.tag_title ASC'));	

		$select->group('y2m_tag.category_id');			
		$statement = $this->adapter->createStatement();
		$select->prepareStatement($this->adapter, $statement);
		//echo $select->getSqlString();exit;
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());	
		return $resultSet->toArray(); 
	}
	public function saveUserTag(UserTag $tag){
       $data = array(
            'user_tag_user_id' => $tag->user_tag_user_id,
            'user_tag_tag_id'  => $tag->user_tag_tag_id,			 
			'user_tag_added_ip_address'  => $tag->user_tag_added_ip_address				
        );
        $user_tag_id = (int)$tag->user_tag_id;
        if ($user_tag_id == 0) {
            $this->insert($data);
			return $this->adapter->getDriver()->getConnection()->getLastGeneratedValue();
        } else {
            if ($this->getUserTag($user_tag_id)) {
                $this->update($data, array('user_tag_id' => $user_tag_id));
            } else {
                throw new \Exception('Form id does not exist');
            }
        }
    }
	public function getUserTag($user_tag_id){
        $select = new Select;
		$select->from('y2m_user_tag')
    		->join('y2m_tag', 'y2m_tag.tag_id = y2m_user_tag.user_tag_tag_id', array('tag_title'))
			->join('y2m_user', 'y2m_user.user_id = y2m_user_tag.user_tag_user_id', array('user_first_name', 'user_last_name'))
			->where(array('y2m_user_tag.user_tag_id' => $user_tag_id));
		$statement = $this->adapter->createStatement();
		$select->prepareStatement($this->adapter, $statement);
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());		 
		return $resultSet;	 
    }
	public function checkUserTag($user_id, $tag_id){
        $user_id  = (int) $user_id;
		$tag_id  = (int) $tag_id;
        $rowset = $this->select(array('user_tag_user_id' => $user_id, 'user_tag_tag_id' => $tag_id));
        return $rowset->current();        
    }
	public function getAllUserTagCategiry($user_id){
		$select = new Select;
		$select->from('y2m_user_tag')
    		->join('y2m_tag', 'y2m_tag.tag_id = y2m_user_tag.user_tag_tag_id',  array())
			->join('y2m_tag_category', 'y2m_tag_category.tag_category_id = y2m_tag.category_id', array('tag_category_title', 'tag_category_icon','tag_category_id'))
			->where(array('y2m_user_tag.user_tag_user_id' => $user_id))
			->where(array('y2m_tag_category.tag_category_status' => 1))			 
			->order(array('y2m_tag.tag_title ASC'));	

		$select->group('y2m_tag.category_id');			
		$statement = $this->adapter->createStatement();
		$select->prepareStatement($this->adapter, $statement);
		//echo $select->getSqlString();exit;
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());	
		return $resultSet->toArray(); 
	}
	public function getAllUserTags($user_id){
		$select = new Select;
		$select->from('y2m_user_tag')
    		->join('y2m_tag', 'y2m_tag.tag_id = y2m_user_tag.user_tag_tag_id',  array('category_id','tag_title','tag_id'))
			->join('y2m_tag_category', 'y2m_tag_category.tag_category_id = y2m_tag.category_id', array())
			->where(array('y2m_user_tag.user_tag_user_id' => $user_id))
			->where(array('y2m_tag_category.tag_category_status' => 1))			 
			->order(array('y2m_tag.tag_title ASC'));		 	
		$statement = $this->adapter->createStatement();
		$select->prepareStatement($this->adapter, $statement);
		//echo $select->getSqlString();exit;
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());	
		return $resultSet->toArray(); 
	}
	public function deleteAllUserTags($user_id,$tag_list=array()){
		if(!empty($tag_list)){
			$sql = "DELETE FROM y2m_user_tag WHERE user_tag_user_id = ".$user_id." AND user_tag_tag_id NOT IN (".implode(',',$tag_list).")"; 
			$statement = $this->adapter-> query($sql); 
			$statement -> execute();
		}else{ 	$this->delete(array('user_tag_user_id' => $user_id)); }		
	}
	public function getAllUserTagsWithIds($user_id){
		$select = new Select;
		$select->from('y2m_user_tag')
    		->join('y2m_tag', 'y2m_tag.tag_id = y2m_user_tag.user_tag_tag_id',  array('tags'=>new Expression('GROUP_CONCAT(y2m_tag.tag_id SEPARATOR \',\')')))
			->join('y2m_tag_category', 'y2m_tag_category.tag_category_id = y2m_tag.category_id', array('tag_category_title', 'tag_category_icon'))
			->where(array('y2m_user_tag.user_tag_user_id' => $user_id))
			->where(array('y2m_tag_category.tag_category_status' => 1))			 
			->order(array('y2m_tag.tag_title ASC'));	

		$select->group('y2m_tag.category_id');			
		$statement = $this->adapter->createStatement();
		$select->prepareStatement($this->adapter, $statement);
		//echo $select->getSqlString();exit;
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());	
		return $resultSet->toArray(); 
	}
	public function getCountOfAllMatchedGroupsofUser($user_id){
		$select = new Select;
		$select->from('y2m_group')
				  ->columns(array(new Expression('COUNT(DISTINCT(y2m_group.group_id)) as group_count')))
				  ->join("y2m_group_tag","y2m_group.group_id = y2m_group_tag.group_tag_group_id",array())
				  ->join("y2m_tag","y2m_tag.tag_id = y2m_group_tag.group_tag_tag_id",array())				  
				  ->where(array("y2m_group.group_status"=>'active'))
				  ->where(array("y2m_group.group_id NOT IN (SELECT user_group_group_id FROM y2m_user_group WHERE user_group_user_id = $user_id)"))
				  ->where(array("y2m_group_tag.group_tag_tag_id IN (SELECT user_tag_tag_id FROM y2m_user_tag WHERE user_tag_user_id = $user_id)"));
		 
		$statement = $this->adapter->createStatement();
		$select->prepareStatement($this->adapter, $statement);
		//echo $select->getSqlString();exit;
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());	
		return $resultSet->current(); 
	}
	public function getmatchGroupsByuserTags($tagIds){
		$select = new Select;
 		$select->from('y2m_group_tag')
			   ->columns(array('y2m_group_tag.group_tag_group_id'=>"group_tag_group_id"))
			   ->where->in("group_tag_tag_id",$tagIds);
		$select->group('y2m_group_tag.group_tag_group_id');  
		$statement = $this->adapter->createStatement();
		$select->prepareStatement($this->adapter, $statement);
	//echo $select->getSqlString();exit;
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());	
		return $resultSet->toArray(); 
	}
}