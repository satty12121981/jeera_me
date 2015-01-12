<?php
namespace Tag\Model;
use Zend\Db\Sql\Select , \Zend\Db\Sql\Where;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Crypt\BlockCipher;	
use Zend\Db\Sql\Expression;
class TagTable extends AbstractTableGateway
{
    protected $table = 'y2m_tag'; 
    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new Tag());

        $this->initialize();
    }
    public function getCountOfAllTags($category,$search=''){
		$select = new Select;
		$select->from('y2m_tag')		
			   ->columns(array(new Expression('COUNT(y2m_tag.tag_id) as tag_count')))
			   ->join("y2m_tag_category","y2m_tag.category_id = y2m_tag_category.tag_category_id",array("tag_category_title"));
		if($category!='all'){
			$select->where(array("y2m_tag_category.tag_category_id"=>$category));
		}
		if($search!=''){
			$select->where->like('y2m_tag.tag_title',$search.'%')->or->like('y2m_tag_category.tag_category_title',$search.'%');		
		}
		$statement = $this->adapter->createStatement();
		$select->prepareStatement($this->adapter, $statement);
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());
		return  $resultSet->current()->tag_count;
	}
	public function getAllTags($limit,$offset,$category,$field="tag_id",$order='ASC',$search=''){ 
		$select = new Select;
		$usersubselect = new select;
		$groupsubselect = new select;		 	
		$usersubselect->from('y2m_user_tag')
			->columns(array(new Expression('COUNT(y2m_user_tag.user_tag_id) as user_count'),'user_tag_tag_id'))
			->group(array('user_tag_tag_id'))
			;
		$groupsubselect->from('y2m_group_tag')
			->columns(array(new Expression('COUNT(y2m_group_tag.group_tag_id) as group_count'),'group_tag_tag_id'))
			->group(array('group_tag_tag_id'))
			;		 
		$select->from('y2m_tag')
				->columns(array('tag_id'=>'tag_id','tag_title'=>'tag_title'))				
				->join("y2m_tag_category","y2m_tag.category_id = y2m_tag_category.tag_category_id",array("tag_category_title"))
				->join(array('temp' => $usersubselect), 'temp.user_tag_tag_id = y2m_tag.tag_id',array('user_count'),'left')
				->join(array('temp1' => $groupsubselect), 'temp1.group_tag_tag_id = y2m_tag.tag_id',array('group_count'),'left');				 
		$select->limit($limit);
		$select->offset($offset);
		$select->order($field.' '.$order);
		if($category!='all'){
			$select->where(array("y2m_tag_category.tag_category_id"=>$category));
		}
		if($search!=''){
			$select->where->like('y2m_tag.tag_title',$search.'%')->or->like('y2m_tag_category.tag_category_title',$search.'%');		
		}
		$statement = $this->adapter->createStatement();
		//echo $select->getSqlString();exit;
		$select->prepareStatement($this->adapter, $statement);
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());			 	
		return  $resultSet->buffer();
	}
	public function getTagByTitle($tag_title)
    {
        $tag_title  = (string) $tag_title;
        $rowset = $this->select(array('tag_title' => $tag_title));
		return $rowset->current();         
    }
	public function saveTag(Tag $tag)
    {
       $data = array(
			'category_id' => $tag->category_id,
            'tag_title' => $tag->tag_title,             
			'tag_added_ip_address'  => $tag->tag_added_ip_address			
        );
        $tag_id = (int)$tag->tag_id;
        if ($tag_id == 0) {
            $this->insert($data);
			return $this->adapter->getDriver()->getConnection()->getLastGeneratedValue();
        } else {
            if ($this->getTag($tag_id)) {
                $this->update($data, array('tag_id' => $tag_id));
            } else {
                throw new \Exception('Form id does not exist');
            }
        }
    }
	public function getTag($tag_id){
        $tag_id  = (int) $tag_id;
        $rowset = $this->select(array('tag_id' => $tag_id));
        return $rowset->current(); 
    }
	public function deleteTag($tag_id){
        $this->delete(array('tag_id' => $tag_id));
    }
	public function getAllCategoryActiveTags($category_id,$search){
		$select = new Select;
		$select->from('y2m_tag')
				->where(array("y2m_tag.category_id"=>$category_id));
		if($search!='')		
				$select->where->like('y2m_tag.tag_title',$search.'%');
		$statement = $this->adapter->createStatement();
		//echo $select->getSqlString();exit;
		$select->prepareStatement($this->adapter, $statement);
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());			 	
		return   $resultSet->toArray();
	}
}