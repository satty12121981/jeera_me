<?php
namespace Groups\Model;
 
use Zend\Db\Sql\Select ;
use Zend\Db\TableGateway\AbstractTableGateway;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\TableGateway\Feature\RowGatewayFeature;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Expression;
class GroupsTable extends AbstractTableGateway
{ 
    protected $table = 'y2m_group';  
    public function __construct(Adapter $adapter){
        $this->adapter = $adapter;
        $this->resultSetPrototype = new ResultSet();
        $this->resultSetPrototype->setArrayObjectPrototype(new Groups());
        $this->initialize();
    }
	public function generalGroupList($limit,$offset,$user=0){
		$sub_select = new Select;
		$sub_select2 = new Select;
		$sub_select->from('y2m_group')
				   ->columns(array(new Expression('COUNT(y2m_group.group_id) as member_count'),"group_id"))
				   ->join(array('y2m_user_group'=>'y2m_user_group'),'y2m_group.group_id = y2m_user_group.user_group_group_id',array());
		$sub_select->group('y2m_group.group_id');
		$select_friend1 = new Select;
		$select_friend1->from('y2m_user_friend')
				   ->columns(array("user_friend_sender_user_id"))
				   ->where(array("user_friend_friend_user_id =".$user));
		$select_friend2 = new Select;
		$select_friend2->from('y2m_user_friend')
				   ->columns(array("user_friend_friend_user_id"))
				   ->where(array("user_friend_sender_user_id=".$user));
		$sub_select2->from('y2m_group')
				   ->columns(array(new Expression('COUNT(y2m_group.group_id) as friend_count'),"group_id"))
				   ->join(array('y2m_user_group'=>'y2m_user_group'),'y2m_group.group_id = y2m_user_group.user_group_group_id',array())
				   ->where->in("user_group_user_id",$select_friend1)->or->in("user_group_user_id",$select_friend2);
		$sub_select2->group('y2m_group.group_id');
		$select = new Select;		
		$select->from('y2m_group')
			   ->columns(array('group_id'=>'group_id',
							 'group_title'=>'group_title',
							 'group_seo_title'=>'group_seo_title',
							 'group_description'=>'group_description',
							 'group_location'=>'group_location',
							 'group_city_id'=>'group_city_id',
							 'group_country_id'=>'group_country_id',
							 'group_location_lat'=>'group_location_lat',
							 'group_location_lng'=>'group_location_lng',
							 'group_web_address'=>'group_web_address',
							 'group_welcome_message_members'=>'group_welcome_message_members',
							 'group_web_address'=>'group_web_address',
							 'is_member'=>new Expression('IF(EXISTS(SELECT * FROM y2m_user_group WHERE y2m_user_group.user_group_user_id = '.$user.' AND y2m_user_group.user_group_group_id = y2m_group.group_id AND y2m_user_group.user_group_status=1),1,0)'),
							 'is_admin'=>new Expression('IF(EXISTS(SELECT * FROM y2m_user_group WHERE y2m_user_group.user_group_user_id = '.$user.' AND y2m_user_group.user_group_group_id = y2m_group.group_id AND y2m_user_group.user_group_is_owner = 1),1,0)')
				))
				->join(array('temp_member' => $sub_select), 'temp_member.group_id = y2m_group.group_id',array('member_count'),'left')
				->join(array('temp_friends' => $sub_select2), 'temp_friends.group_id = y2m_group.group_id',array('friend_count'),'left')
				->join("y2m_country","y2m_country.country_id = y2m_group.group_country_id",array("country_code_googlemap","country_title","country_code"),'left')
			    ->join("y2m_city","y2m_city.city_id = y2m_group.group_city_id",array("city"=>"name"),'left')
				->join("y2m_group_photo","y2m_group_photo.group_photo_group_id = y2m_group.group_id",array("group_photo_photo"=>"group_photo_photo"),'left')
				->where(array("y2m_group.group_status = 'active'"));
		$select->order(array('member_count DESC'));
		$select->limit($limit);
		$select->offset($offset);
		$statement = $this->adapter->createStatement();
		//echo $select->getSqlString();exit;
		$select->prepareStatement($this->adapter, $statement);		 
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());	
		return $resultSet->toArray();	
	}
	public function getPlanetinfo($group_id){
		$select = new Select;
		$predicate = new  \Zend\Db\Sql\Where();
		$select->from('y2m_group')
		 ->where(array('y2m_group.group_id' => $group_id));
		$statement = $this->adapter->createStatement();
		$select->prepareStatement($this->adapter, $statement);
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());
		return $resultSet->current();
	}
	public function getGroupByName($group_title){         
        $rowset = $this->select(array('group_title' => $group_title));
        $row = $rowset->current();
		return $row;
    }
	public function saveGroupBasicDetails(Groups $objGroup, $intGroupId) {
         $data = array(
            'group_title'           => addslashes($objGroup->strGroupName),
            'group_seo_title'       => addslashes($objGroup->group_seo_title),
			'group_status'          => 'active',
			'group_description'     => addslashes($objGroup->strDesp),
			'group_city_id'         => $objGroup->intCityId,
			'group_country_id'      => $objGroup->intCountryId,
			'group_type'      => $objGroup->intGroupType
		);
        if($intGroupId != ''){
			$this->update($data, array('group_id' => $intGroupId));
			return $intGroupId;
        }else {
			$this->insert($data);
			return $this->adapter->getDriver()->getConnection()->getLastGeneratedValue();
        }
    }
	public function getGroupForSEO($group_seo_title){
      	$rowset = $this->select(array('group_seo_title' => $group_seo_title,'group_status'=>'active'));
		$row = $rowset->current();
        return $row;
    }
	public function getNewsFeeds($user_id,$type,$group_id,$activity,$limit,$offset){
		
		$result = new ResultSet();
		if($activity=='goingto'){
			$type = 'Event';
		}
		switch($type){
			case 'Text':
				$sql = 'SELECT group_discussion_id as event_id,group_discussion_added_timestamp as update_time,if(group_discussion_id,"New Status","") as type,user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id  FROM  y2m_group_discussion INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_discussion.group_discussion_owner_user_id INNER JOIN y2m_group ON y2m_group_discussion.group_discussion_group_id = y2m_group.group_id  LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE group_discussion_group_id IN (SELECT user_group_group_id FROM y2m_user_group WHERE user_group_user_id = '.$user_id.' AND user_group_status = "available" ) AND group_discussion_status = "available" AND y2m_user.user_status = "live" AND group_status = "active"';
				if($group_id!=''){
					$sql.=' AND group_id = '.$group_id;
				}
				if($activity == 'Interactions'){
					$sql.=' AND (group_discussion_id IN (SELECT comment_refer_id FROM y2m_comment WHERE comment_system_type_id = 2 AND comment_by_user_id = '.$user_id.') OR group_discussion_id IN(SELECT like_refer_id FROM y2m_like WHERE like_system_type_id = 2 AND like_by_user_id = '.$user_id.')) ' ;
				}
				if($activity == 'friends_post'){
					$sql.=' AND group_discussion_owner_user_id IN (SELECT  IF(user_friend_sender_user_id='.$user_id.',user_friend_friend_user_id,user_friend_sender_user_id) as friend_user FROM y2m_user_friend WHERE user_friend_sender_user_id = '.$user_id.' OR user_friend_friend_user_id = '.$user_id.' )  ' ;
				}
			break;
			case 'Media':
				$sql = 'SELECT group_media_id as event_id,media_added_date as update_time,if(group_media_id,"New Media","") as type,user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id FROM  y2m_group_media INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_media.media_added_user_id INNER JOIN y2m_group ON y2m_group_media.media_added_group_id = y2m_group.group_id LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE media_added_group_id IN (SELECT user_group_group_id FROM y2m_user_group WHERE user_group_user_id = '.$user_id.' AND user_group_status = "available" ) AND media_status = "active"  AND y2m_user.user_status = "live" AND group_status = "active"';
				if($group_id!=''){
					$sql.=' AND group_id = '.$group_id;
				}
				if($activity == 'Interactions'){
					$sql.=' AND (group_media_id IN (SELECT comment_refer_id FROM y2m_comment WHERE comment_system_type_id = 4 AND comment_by_user_id = '.$user_id.') OR group_media_id IN(SELECT like_refer_id FROM y2m_like WHERE like_system_type_id = 4 AND like_by_user_id = '.$user_id.')) ' ;
				}
				if($activity == 'friends_post'){
					$sql.=' AND media_added_user_id IN (SELECT  IF(user_friend_sender_user_id='.$user_id.',user_friend_friend_user_id,user_friend_sender_user_id) as friend_user FROM y2m_user_friend WHERE user_friend_sender_user_id = '.$user_id.' OR user_friend_friend_user_id = '.$user_id.' ) ' ;
				}
			break;
			case 'Event':
				$sql = 'SELECT group_activity_id as event_id,group_activity_added_timestamp as update_time,if(group_activity_id,"New Activity","") as type,	user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id FROM  y2m_group_activity INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_activity.group_activity_owner_user_id 	INNER JOIN y2m_group ON y2m_group_activity.group_activity_group_id = y2m_group.group_id LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE group_activity_group_id IN (SELECT user_group_group_id FROM y2m_user_group WHERE user_group_user_id = '.$user_id.' AND user_group_status = "available") AND group_activity_status = "active" AND y2m_user.user_status = "live" AND group_status = "active"';
				if($group_id!=''){
					$sql.=' AND group_id = '.$group_id;
				}
				if($activity == 'Interactions'){
					$sql.=' AND (group_activity_id IN (SELECT comment_refer_id FROM y2m_comment WHERE comment_system_type_id = 1 AND comment_by_user_id = '.$user_id.') OR group_activity_id IN(SELECT like_refer_id FROM y2m_like WHERE like_system_type_id = 1 AND like_by_user_id = '.$user_id.')) ' ;
				}
				if($activity == 'friends_post'){
					$sql.=' AND group_activity_owner_user_id IN (SELECT  IF(user_friend_sender_user_id='.$user_id.',user_friend_friend_user_id,user_friend_sender_user_id) as friend_user FROM y2m_user_friend WHERE user_friend_sender_user_id = '.$user_id.' OR user_friend_friend_user_id = '.$user_id.' ) ' ;
				}
				if($activity == 'goingto'){
					$sql.=' AND group_activity_id IN (SELECT group_activity_rsvp_activity_id FROM y2m_group_activity_rsvp WHERE group_activity_rsvp_user_id = '.$user_id.') ' ;
				}
			break;
			default :
				$sql = 'SELECT group_activity_id as event_id,group_activity_added_timestamp as update_time,if(group_activity_id,"New Activity","") as type,	user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id FROM  y2m_group_activity INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_activity.group_activity_owner_user_id 	INNER JOIN y2m_group ON y2m_group_activity.group_activity_group_id = y2m_group.group_id LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE group_activity_group_id IN (SELECT user_group_group_id FROM y2m_user_group WHERE user_group_user_id = '.$user_id.' AND user_group_status = "available") AND group_activity_status = "active" AND y2m_user.user_status = "live" AND group_status = "active"';
				if($group_id!=''){
					$sql.=' AND group_id = '.$group_id;
				}
				if($activity == 'Interactions'){
					$sql.=' AND (group_activity_id IN (SELECT comment_refer_id FROM y2m_comment WHERE comment_system_type_id = 1 AND comment_by_user_id = '.$user_id.') OR group_activity_id IN(SELECT like_refer_id FROM y2m_like WHERE like_system_type_id = 1 AND like_by_user_id = '.$user_id.')) ' ;
				}
				if($activity == 'friends_post'){
					$sql.=' AND group_activity_owner_user_id IN (SELECT  IF(user_friend_sender_user_id='.$user_id.',user_friend_friend_user_id,user_friend_sender_user_id) as friend_user FROM y2m_user_friend WHERE user_friend_sender_user_id = '.$user_id.' OR user_friend_friend_user_id = '.$user_id.' ) ' ;
				}
				$sql.=' UNION
				SELECT group_discussion_id as event_id,group_discussion_added_timestamp as update_time,if(group_discussion_id,"New Status","") as type,user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id  FROM  y2m_group_discussion INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_discussion.group_discussion_owner_user_id INNER JOIN y2m_group ON y2m_group_discussion.group_discussion_group_id = y2m_group.group_id  LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE group_discussion_group_id IN (SELECT user_group_group_id FROM y2m_user_group WHERE user_group_user_id = '.$user_id.' AND user_group_status = "available" ) AND group_discussion_status = "available" AND y2m_user.user_status = "live" AND group_status = "active"';
				if($group_id!=''){
					$sql.=' AND group_id = '.$group_id;
				}
				if($activity == 'Interactions'){
					$sql.=' AND (group_discussion_id IN (SELECT comment_refer_id FROM y2m_comment WHERE comment_system_type_id = 2 AND comment_by_user_id = '.$user_id.') OR group_discussion_id IN(SELECT like_refer_id FROM y2m_like WHERE like_system_type_id = 2 AND like_by_user_id = '.$user_id.')) ' ;
				}
				if($activity == 'friends_post'){
					$sql.=' AND group_discussion_owner_user_id IN (SELECT  IF(user_friend_sender_user_id='.$user_id.',user_friend_friend_user_id,user_friend_sender_user_id) as friend_user FROM y2m_user_friend WHERE user_friend_sender_user_id = '.$user_id.' OR user_friend_friend_user_id = '.$user_id.' ) ' ;
				}
				$sql.='
				 UNION
				SELECT group_media_id as event_id,media_added_date as update_time,if(group_media_id,"New Media","") as type,user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id FROM  y2m_group_media INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_media.media_added_user_id INNER JOIN y2m_group ON y2m_group_media.media_added_group_id = y2m_group.group_id LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE media_added_group_id IN (SELECT user_group_group_id FROM y2m_user_group WHERE user_group_user_id = '.$user_id.' AND user_group_status = "available" ) AND media_status = "active"  AND y2m_user.user_status = "live" AND group_status = "active"';
				if($group_id!=''){
					$sql.=' AND group_id = '.$group_id;
				}
				if($activity == 'Interactions'){
					$sql.=' AND (group_media_id IN (SELECT comment_refer_id FROM y2m_comment WHERE comment_system_type_id = 4 AND comment_by_user_id = '.$user_id.') OR group_media_id IN(SELECT like_refer_id FROM y2m_like WHERE like_system_type_id =4 AND like_by_user_id = '.$user_id.')) ' ;
				}
				if($activity == 'friends_post'){
					$sql.=' AND media_added_user_id IN (SELECT  IF(user_friend_sender_user_id='.$user_id.',user_friend_friend_user_id,user_friend_sender_user_id) as friend_user FROM y2m_user_friend WHERE user_friend_sender_user_id = '.$user_id.' OR user_friend_friend_user_id = '.$user_id.' ) ' ;
				}				
			
		}
		//echo $sql;die();
		$sql.=' ORDER BY update_time DESC LIMIT '.$offset.','.$limit; 		 
		$statement = $this->adapter-> query($sql); 		 
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());
		return $resultSet->toArray();
	}
	public function getMyFeeds($user_id,$type,$limit,$offset){
		
		$result = new ResultSet();
		
		switch($type){
			case 'Text':
				$sql = 'SELECT group_discussion_id as event_id,group_discussion_added_timestamp as update_time,if(group_discussion_id,"New Status","") as type,user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id  FROM  y2m_group_discussion INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_discussion.group_discussion_owner_user_id INNER JOIN y2m_group ON y2m_group_discussion.group_discussion_group_id = y2m_group.group_id  LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE group_discussion_group_id IN (SELECT user_group_group_id FROM y2m_user_group WHERE user_group_user_id = '.$user_id.' AND user_group_status = "available" ) AND group_discussion_status = "available" AND y2m_user.user_status = "live" AND group_status = "active" AND y2m_group_discussion.group_discussion_owner_user_id='.$user_id;
				 
			break;
			case 'Media':
				$sql = 'SELECT group_media_id as event_id,media_added_date as update_time,if(group_media_id,"New Media","") as type,user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id FROM  y2m_group_media INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_media.media_added_user_id INNER JOIN y2m_group ON y2m_group_media.media_added_group_id = y2m_group.group_id LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE media_added_group_id IN (SELECT user_group_group_id FROM y2m_user_group WHERE user_group_user_id = '.$user_id.' AND user_group_status = "available" ) AND media_status = "active"  AND y2m_user.user_status = "live" AND group_status = "active" AND  y2m_group_media.media_added_user_id='.$user_id;
				 
			break;
			case 'Event':
				$sql = 'SELECT group_activity_id as event_id,group_activity_added_timestamp as update_time,if(group_activity_id,"New Activity","") as type,	user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id FROM  y2m_group_activity INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_activity.group_activity_owner_user_id 	INNER JOIN y2m_group ON y2m_group_activity.group_activity_group_id = y2m_group.group_id LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE group_activity_group_id IN (SELECT user_group_group_id FROM y2m_user_group WHERE user_group_user_id = '.$user_id.' AND user_group_status = "available") AND group_activity_status = "active" AND y2m_user.user_status = "live" AND group_status = "active" AND y2m_group_activity.group_activity_owner_user_id ='.$user_id;
				 
			break;
			default :
				$sql = 'SELECT group_activity_id as event_id,group_activity_added_timestamp as update_time,if(group_activity_id,"New Activity","") as type,	user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id FROM  y2m_group_activity INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_activity.group_activity_owner_user_id 	INNER JOIN y2m_group ON y2m_group_activity.group_activity_group_id = y2m_group.group_id LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE group_activity_group_id IN (SELECT user_group_group_id FROM y2m_user_group WHERE user_group_user_id = '.$user_id.' AND user_group_status = "available") AND group_activity_status = "active" AND y2m_user.user_status = "live" AND group_status = "active" AND y2m_group_activity.group_activity_owner_user_id ='.$user_id;
				 
				$sql.=' UNION
				SELECT group_discussion_id as event_id,group_discussion_added_timestamp as update_time,if(group_discussion_id,"New Status","") as type,user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id  FROM  y2m_group_discussion INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_discussion.group_discussion_owner_user_id INNER JOIN y2m_group ON y2m_group_discussion.group_discussion_group_id = y2m_group.group_id  LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE group_discussion_group_id IN (SELECT user_group_group_id FROM y2m_user_group WHERE user_group_user_id = '.$user_id.' AND user_group_status = "available" ) AND group_discussion_status = "available" AND y2m_user.user_status = "live" AND group_status = "active" AND y2m_group_discussion.group_discussion_owner_user_id='.$user_id; 
				$sql.='
				 UNION
				SELECT group_media_id as event_id,media_added_date as update_time,if(group_media_id,"New Media","") as type,user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id FROM  y2m_group_media INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_media.media_added_user_id INNER JOIN y2m_group ON y2m_group_media.media_added_group_id = y2m_group.group_id LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE media_added_group_id IN (SELECT user_group_group_id FROM y2m_user_group WHERE user_group_user_id = '.$user_id.' AND user_group_status = "available" ) AND media_status = "active"  AND y2m_user.user_status = "live" AND group_status = "active" AND  y2m_group_media.media_added_user_id='.$user_id;
			
		}
		//echo $sql;die();
		$sql.=' ORDER BY update_time DESC LIMIT '.$offset.','.$limit; 		 
		$statement = $this->adapter-> query($sql); 		 
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());
		return $resultSet->toArray();
	}
	public function getMyActivity($user_id,$type,$limit,$offset){
		
		$result = new ResultSet();
		
		switch($type){
			case 'Comments':
				$sql = 'SELECT group_discussion_id as event_id,group_discussion_added_timestamp as update_time,if(group_discussion_id,"New Status","") as type,user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id  FROM  y2m_group_discussion INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_discussion.group_discussion_owner_user_id INNER JOIN y2m_group ON y2m_group_discussion.group_discussion_group_id = y2m_group.group_id  LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE group_discussion_id IN (SELECT comment_refer_id FROM y2m_comment WHERE y2m_comment.comment_system_type_id =2 AND comment_by_user_id ='.$user_id.' )';
				$sql.='
				 UNION
				SELECT group_media_id as event_id,media_added_date as update_time,if(group_media_id,"New Media","") as type,user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id FROM  y2m_group_media INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_media.media_added_user_id INNER JOIN y2m_group ON y2m_group_media.media_added_group_id = y2m_group.group_id LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id  WHERE group_media_id IN (SELECT comment_refer_id FROM y2m_comment WHERE y2m_comment.comment_system_type_id =4 AND comment_by_user_id ='.$user_id.' )';
				$sql.='
				 UNION
				 SELECT group_activity_id as event_id,group_activity_added_timestamp as update_time,if(group_activity_id,"New Activity","") as type,	user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id FROM  y2m_group_activity INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_activity.group_activity_owner_user_id 	INNER JOIN y2m_group ON y2m_group_activity.group_activity_group_id = y2m_group.group_id LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE group_activity_id IN (SELECT comment_refer_id FROM y2m_comment WHERE y2m_comment.comment_system_type_id =1 AND comment_by_user_id ='.$user_id.' )';
				 
			break;
			case 'Likes':
				$sql = 'SELECT group_discussion_id as event_id,group_discussion_added_timestamp as update_time,if(group_discussion_id,"New Status","") as type,user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id  FROM  y2m_group_discussion INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_discussion.group_discussion_owner_user_id INNER JOIN y2m_group ON y2m_group_discussion.group_discussion_group_id = y2m_group.group_id  LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE group_discussion_id IN (SELECT like_refer_id FROM y2m_like WHERE y2m_like.like_system_type_id =2 AND like_by_user_id ='.$user_id.' )';
				$sql.='
				 UNION
				SELECT group_media_id as event_id,media_added_date as update_time,if(group_media_id,"New Media","") as type,user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id FROM  y2m_group_media INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_media.media_added_user_id INNER JOIN y2m_group ON y2m_group_media.media_added_group_id = y2m_group.group_id LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id  WHERE group_media_id IN (SELECT like_refer_id FROM y2m_like WHERE y2m_like.like_system_type_id =4 AND like_by_user_id ='.$user_id.' )';
				$sql.='
				 UNION
				 SELECT group_activity_id as event_id,group_activity_added_timestamp as update_time,if(group_activity_id,"New Activity","") as type,	user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id FROM  y2m_group_activity INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_activity.group_activity_owner_user_id 	INNER JOIN y2m_group ON y2m_group_activity.group_activity_group_id = y2m_group.group_id LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE group_activity_id IN (SELECT like_refer_id FROM y2m_like WHERE y2m_like.like_system_type_id =1 AND like_by_user_id ='.$user_id.' )';
				 
			break;			 
			default :
				$sql = 'SELECT group_discussion_id as event_id,group_discussion_added_timestamp as update_time,if(group_discussion_id,"New Status","") as type,user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id  FROM  y2m_group_discussion INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_discussion.group_discussion_owner_user_id INNER JOIN y2m_group ON y2m_group_discussion.group_discussion_group_id = y2m_group.group_id  LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE group_discussion_id IN (SELECT comment_refer_id FROM y2m_comment WHERE y2m_comment.comment_system_type_id =2 AND comment_by_user_id ='.$user_id.' ) OR group_discussion_id IN (SELECT like_refer_id FROM y2m_like WHERE y2m_like.like_system_type_id =2 AND like_by_user_id ='.$user_id.' )';
				$sql.='
				 UNION
				SELECT group_media_id as event_id,media_added_date as update_time,if(group_media_id,"New Media","") as type,user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id FROM  y2m_group_media INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_media.media_added_user_id INNER JOIN y2m_group ON y2m_group_media.media_added_group_id = y2m_group.group_id LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id  WHERE group_media_id IN (SELECT comment_refer_id FROM y2m_comment WHERE y2m_comment.comment_system_type_id =4 AND comment_by_user_id ='.$user_id.' ) OR group_media_id IN (SELECT like_refer_id FROM y2m_like WHERE y2m_like.like_system_type_id =4 AND like_by_user_id ='.$user_id.' )';
				$sql.='
				 UNION
				 SELECT group_activity_id as event_id,group_activity_added_timestamp as update_time,if(group_activity_id,"New Activity","") as type,	user_id,user_given_name,user_profile_name,profile_photo,group_title,group_seo_title,group_id FROM  y2m_group_activity INNER JOIN y2m_user ON y2m_user.user_id = y2m_group_activity.group_activity_owner_user_id 	INNER JOIN y2m_group ON y2m_group_activity.group_activity_group_id = y2m_group.group_id LEFT JOIN y2m_user_profile_photo ON y2m_user.user_profile_photo_id = y2m_user_profile_photo.profile_photo_id WHERE group_activity_id IN (SELECT comment_refer_id FROM y2m_comment WHERE y2m_comment.comment_system_type_id =1 AND comment_by_user_id ='.$user_id.' ) OR group_activity_id IN (SELECT like_refer_id FROM y2m_like WHERE y2m_like.like_system_type_id =1 AND like_by_user_id ='.$user_id.' )';
			
		}
		//echo $sql;die();
		$sql.=' ORDER BY update_time DESC LIMIT '.$offset.','.$limit; 		 
		$statement = $this->adapter-> query($sql); 		 
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());
		return $resultSet->toArray();
	}
	public function fetchSystemType($SystemTypeTitle){
		$SystemTypeTitle  = (string) $SystemTypeTitle;
		$table = new TableGateway('y2m_system_type', $this->adapter, new RowGatewayFeature('system_type_title'));
		$results = $table->select(array('system_type_title' => $SystemTypeTitle));
		$Row = $results->current();
		return $Row;
    }
	public function checkSeotitleExist($seotitle){
		$select = new Select;
		$select->from('y2m_group')
			   ->columns(array('group_id'))
			   ->where(array("group_seo_title"=>$seotitle));
		$statement = $this->adapter->createStatement();
		$select->prepareStatement($this->adapter, $statement);	
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());	
		$row = $resultSet->current();
		if(!empty($row)&&$row->group_id!=''){
			return true;
		}else{
			return false;
		}
	}
	public function getGroupBySeoTitle($group_seo){
        $rowset = $this->select(array('group_seo_title' => $group_seo,'group_status'=>'active'));
        $row = $rowset->current();
		return $row;
    }
	public function getGroupDetails($group_id,$user_id){
		$sub_select = new Select;	
		$subselect2 = new Select;	
		$sub_select3 = new Select;	
		$sub_select->from('y2m_group')
				   ->columns(array(new Expression('COUNT(y2m_group.group_id) as member_count'),"group_id"))
				   ->join(array('y2m_user_group'=>'y2m_user_group'),'y2m_group.group_id = y2m_user_group.user_group_group_id',array());
		$sub_select->group('y2m_group.group_id');
		$expression = new Expression(
            "IF (`user_friend_sender_user_id`= $user_id , `user_friend_friend_user_id`, `user_friend_sender_user_id`)"
        );
        $subselect2->from('y2m_user_friend')
            ->columns(array('friend_id'=>$expression))
            ->where->equalTo('user_friend_sender_user_id', $user_id)->OR->equalTo('user_friend_friend_user_id', $user_id)
           ;
		$sub_select3->from('y2m_group')
				   ->columns(array(new Expression('COUNT(y2m_group.group_id) as friend_count'),"group_id"))
				   ->join(array('y2m_user_group'=>'y2m_user_group'),'y2m_group.group_id = y2m_user_group.user_group_group_id',array())
				   ->where->in("user_group_user_id",$subselect2);
		$sub_select3->group('y2m_group.group_id');
		$select = new Select;
		$select->from('y2m_group')
			   ->columns(array('group_id','group_title','group_seo_title','group_description','group_added_timestamp','group_type','is_admin'=>new Expression('IF(EXISTS(SELECT * FROM y2m_user_group WHERE  (y2m_user_group.user_group_group_id = y2m_group.group_id AND y2m_user_group.user_group_user_id = '.$user_id.' AND y2m_user_group.user_group_is_owner = 1)),1,0)'),
			   'is_member'=>new Expression('IF(EXISTS(SELECT * FROM y2m_user_group WHERE  (y2m_user_group.user_group_group_id = y2m_group.group_id AND y2m_user_group.user_group_user_id = '.$user_id.' AND y2m_user_group.user_group_is_owner = 0)),1,0)'),
			   'is_requested'=>new Expression('IF(EXISTS(SELECT * FROM y2m_user_group_joining_request WHERE  (y2m_user_group_joining_request.user_group_joining_request_group_id = y2m_group.group_id AND y2m_user_group_joining_request.user_group_joining_request_user_id = '.$user_id.' AND y2m_user_group_joining_request.user_group_joining_request_status = "active")),1,0)')))
			   ->join(array('temp_member' => $sub_select), 'temp_member.group_id = y2m_group.group_id',array('member_count'),'left')
			   ->join(array('temp_friends' => $sub_select3), 'temp_friends.group_id = y2m_group.group_id',array('friend_count'),'left')
			   ->join("y2m_country","y2m_country.country_id = y2m_group.group_country_id",array("country_code_googlemap","country_title","country_code"),'left')
			   ->join("y2m_city","y2m_city.city_id = y2m_group.group_city_id",array("city"=>"name"),'left')
			   ->join("y2m_group_photo","y2m_group_photo.group_photo_group_id = y2m_group.group_id",array("group_photo_photo"=>"group_photo_photo"),'left')
			   ->where(array("y2m_group.group_status = 'active'","y2m_group.group_id = ".$group_id));
		$statement = $this->adapter->createStatement();
		$select->prepareStatement($this->adapter, $statement);	
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());	
		$row = $resultSet->current();
		return $row;
	}
	public function updateGroup($data,$group_id){
		 $this->update($data, array('group_id' => $group_id));
		return true;
	}
	public function searchGroup($search,$limit,$offset){
		$select = new Select;
		$select->from('y2m_group')
				 ->columns(array('group_id','group_title','group_seo_title','group_description','group_added_timestamp','group_type'))
				 ->join("y2m_country","y2m_country.country_id = y2m_group.group_country_id",array("country_code_googlemap","country_title","country_code"),'left')
			     ->join("y2m_city","y2m_city.city_id = y2m_group.group_city_id",array("city"=>"name"),'left')
			     ->join("y2m_group_photo","y2m_group_photo.group_photo_group_id = y2m_group.group_id",array("group_photo_photo"=>"group_photo_photo"),'left')
			     ->where(array("y2m_group.group_status = 'active'","y2m_group.group_title LIKE '%".$search."%'"));
		$select->limit($limit);
		$select->offset($offset);				 
		$statement = $this->adapter->createStatement();
		$select->prepareStatement($this->adapter, $statement);	
		$resultSet = new ResultSet();
		$resultSet->initialize($statement->execute());	
		return $resultSet->toArray();
		
	}
}