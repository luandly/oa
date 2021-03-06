<?php 
/**
	极光推送
*/
include_once(ROOT_PATH.'/include/JPush/JPush.php');
class JPushChajian extends Chajian{
	
	//有自己的推送key填写这里，默认自己是没有啊
	private $app_key 		= '';
	private $master_secret 	= '';
	
	
	
	private $push_url 		= '';
	private $push_urls 		= 'aHR0cDovLzEyNy4wLjAuMS9hcHAvcm9ja3hpbmh1d2ViLw::';
	
	
	protected function initChajian()
	{
		if(getconfig('systype')=='dev'){
			$this->push_url = $this->rock->jm->base64decode($this->push_urls);
		}else{
			$this->push_url = URLY;
		}
		$this->push_url.= 'api.php?a=jpush';
		$this->push_url.= '&version='.VERSION.'&rnd='.time().'';
	}
	
	//获取推送到某个用户
	private function getalias($uid, $lx=0)
	{
		if($uid=='')return false;
		$where 	= "id in($uid) and ";
		if($uid=='all'){
			$where='';
		}else{
			if($this->contain($uid,'u') || $this->contain($uid,'d')){
				$uid = m('admin')->gjoin($uid);
				if($uid=='')return false;
				$where 	= "id in($uid) and ";
			}
		}
		$wheres = '';
		if($lx==1){
			$stal 	= date('Y-m-d H:i:s', time()-5*60);
			$wheres = "and ifnull(`lastpush`,'')<'$stal'";
		}
		$uwhere = "$where `status`=1 and `apptx`=1 $wheres";
		$rows 	= m('logintoken')->getrows("`uid` in(select id from `[Q]admin` where $uwhere) and `cfrom` in ('appandroid','appios') and `online`=1",'`token`,`uid`');
		$alias 	= array();
		$uids	= '0';
		foreach($rows as $k=>$rs){
			$alias[] = $rs['token'];
			$uids	.= ','.$rs['uid'].'';
		}
		return array('alias' => $alias, 'uids'=>$uids);
	}
	
	public function send($uid, $title='', $cont='', $lx=0)
	{
		$garr = $this->getalias($uid, $lx);
		if(!$garr)return false;
		$alias	= $garr['alias'];
		$uids	= $garr['uids'];
		if($uids=='0')return false;
		if($this->app_key=='' || $this->master_secret==''){
			$result = c('curl')->postcurl($this->push_url, array(
				'alias' => join(',', $alias),
				'uids'  => $uids,
				'title' => $this->rock->jm->base64encode($title),
				'cont'  => $this->rock->jm->base64encode($cont)
			));
			$result = json_encode($result, true);
		}else{
			$client = new JPush($this->app_key, $this->master_secret);
			$obj 	= $client->push()->setPlatform('all');
			$obj->addAlias($alias);
			$result	= $obj
				->setNotificationAlert($cont)
				->addAndroidNotification($cont, $title, 1, array())
				->addIosNotification($cont)
				->send();
		}		
		$this->db->update('[Q]admin',"`lastpush`='".$this->rock->now."'", "id in($uids)");
		return $result;
	}
	
	
	
	//-------------最新原生app推送
	public function push($title,$desc, $cont, $palias)
	{
		//使用官网来推送
		$alias		= $palias['alias'];
		$xmalias	= $palias['xmalias']; //小米的
		$uids		= $palias['uids'];
		if($this->app_key=='' || $this->master_secret==''){
			$arr 	= array(
				'alias' 	=> join(',', $alias),
				'xmalias' 	=> join(',', $xmalias),
				'uids'  => $uids,
				'title' => $this->rock->jm->base64encode($title),
				'cont'  => $this->rock->jm->base64encode($cont),
				'desc'  => $desc
			);
			$runurl = c('xinhu')->geturlstr('jpushplat', $arr);
			return c('curl')->getcurl($runurl);
		}else{
			$barr['jpush'] = $this->sendMessage($alias, $title, $cont);
			//小米推送的
			if($xmalias){
				$xmpush = c('xiaomiPush');
				if(method_exists($xmpush,'push'))
					$barr['xmpush'] = $xmpush->push($xmalias,  $title, $this->rock->jm->base64decode($desc), $cont);
			}
			$this->rock->debugs(json_encode($barr),'jpush');
			return $barr;
		}	
	}
	
	//发送消息的推送
	public function sendMessage($alias, $title='', $cont='')
	{
		if(!$alias)return false;
		$client = new JPush($this->app_key, $this->master_secret);
		$obj 	= $client->push()->setPlatform('all');
		$obj->addAlias($alias);
		$result	= $obj
			->setMessage($cont, $title) //发信息
			->setOptions(null, 0)		//不保存离线消息
			->send();
		$msg = json_encode($result);	
		return $result;
	}
}