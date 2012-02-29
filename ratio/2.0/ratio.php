<?php
rRatio::$rootPath = "./";
$ratioThisPath = "./plugins/ratio/";
if(!is_file('util.php'))
{
	rRatio::$rootPath = "../../";
	$ratioThisPath = "./";
}
require_once( rRatio::$rootPath."xmlrpc.php" );
require_once( $ratioThisPath."conf.php" );

define('RAT_STOP',0);
define('RAT_STOP_AND_REMOVE',1);
define('RAT_ERASE',2);

class rRatio
{
	public $hash = "ratio.dat";
	public $rat = array();
	static public $rootPath;

	static public function load()
	{
		global $settings;
		$cache = new rCache( self::$rootPath.$settings );
		$rt = new rRatio();
		if(!$cache->get($rt))
			$rt->fillArray();
		return($rt);
	}
	public function fillArray()
	{
		$this->rat = array();
	        for($i=0; $i<MAX_RATIO; $i++)
			$this->rat[] = array( "action"=>RAT_STOP, "min"=>100, "max"=>300, "upload"=>20, "name"=>"ratio".$i );
	}
	public function isCorrect($no)
	{
		return( ($no<count($this->rat)) &&
		        ($this->rat[$no]["name"]!=""));

	}
	public function correct()
	{
		$cmd = new rXMLRPCCommand("d.multicall",array("default","d.get_hash="));
		for($i=0; $i<MAX_RATIO; $i++)
		{
			$cmd->addParameter("d.views.has=rat_".$i);
			$cmd->addParameter("view.set_not_visible=rat_".$i);
		}
		$req = new rXMLRPCRequest($cmd);
		if($req->run() && !$req->fault)
		{
			$req1 = new rXMLRPCRequest();
			foreach($req->strings as $no=>$hash)
			{
			        for($i=0; $i<MAX_RATIO; $i++)
			        {
					if($req->i8s[$no*MAX_RATIO*2+$i*2]==1)
						$req1->addCommand(new rXMLRPCCommand("view.set_visible",array($hash,"rat_".$i)));
				}						
			}
			return(($req1->getCommandsCount()==0) || ($req1->run() && !$req1->fault));
		}
		return(false);
	}
	public function obtain()
	{
	        global $isAutoStart;
	        if($isAutoStart)
	        	return($this->flush() && $this->correct());
	        else
	        {
		        $req = new rXMLRPCRequest();
			for($i = 0; $i<MAX_RATIO; $i++)
			{
				$req->addCommand(new rXMLRPCCommand("group.rat_".$i.".ratio.min"));
				$req->addCommand(new rXMLRPCCommand("group.rat_".$i.".ratio.max"));
				$req->addCommand(new rXMLRPCCommand("group.rat_".$i.".ratio.upload"));
			}
			if($req->run() && !$req->fault)
			{
				for($i = 0; $i<MAX_RATIO; $i++)
				{
				        if($i>=count($this->rat))
				                $this->rat[$i] = array("action"=>RAT_STOP, "name"=>"ratio".$i );
					$this->rat[$i]["min"] = $req->i8s[$i*3];
					$this->rat[$i]["max"] = $req->i8s[$i*3+1];
					$this->rat[$i]["upload"] = floatval($req->i8s[$i*3+2])/1024/1024;
				}
				return($this->store());
			}
	                return(false);
		}
	}
	public function flush()
	{
		$req1 = new rXMLRPCRequest(new rXMLRPCCommand("view_list"));
		if($req1->run() && !$req1->fault)
		{
			$req = new rXMLRPCRequest();
			for($i=0; $i<MAX_RATIO; $i++)
			{
				$rat = $this->rat[$i];
				if(!in_array("rat_".$i,$req1->strings))
					$req->addCommand(new rXMLRPCCommand("group.insert_persistent_view", array("", "rat_".$i)));
				if($this->isCorrect($i))
				{
					$req->addCommand(new rXMLRPCCommand("group.rat_".$i.".ratio.enable",array("")));
					$req->addCommand(new rXMLRPCCommand("group.rat_".$i.".ratio.min.set",$rat["min"]));
					$req->addCommand(new rXMLRPCCommand("group.rat_".$i.".ratio.max.set",$rat["max"]));
					$req->addCommand(new rXMLRPCCommand("group.rat_".$i.".ratio.upload.set",floatval($rat["upload"]*1024*1024)));
					switch($rat["action"])
					{
						case RAT_STOP:
						{
							$req->addCommand(new rXMLRPCCommand("system.method.set", array("group.rat_".$i.".ratio.command", "d.stop=; d.close=")));
							break;
						}
						case RAT_STOP_AND_REMOVE:
						{
							$req->addCommand(new rXMLRPCCommand("system.method.set", array("group.rat_".$i.".ratio.command", "d.stop=; d.close=; view.set_not_visible=rat_".$i."; d.views.remove=rat_".$i)));
							break;
						}
						case RAT_ERASE:
						{
							$req->addCommand(new rXMLRPCCommand("system.method.set", array("group.rat_".$i.".ratio.command", "d.stop=; d.close=; d.erase=")));
							break;
						}
					}
				}
			}
			return(($req->getCommandsCount()==0) || ($req->run() && !$req->fault));
		}
		return(false);
	}
	public function store()
	{
		global $settings;
		$cache = new rCache( self::$rootPath.$settings );
		return($cache->set($this));
	}

	public function set()
	{
		$this->rat = array();
		for($i = 0; $i<MAX_RATIO; $i++)
		{
			$arr = array( "action"=>RAT_STOP, "min"=>100, "max"=>300, "upload"=>20, "name"=>"" );
			if(isset($_REQUEST['rat_action'.$i]))
				$arr["action"] = intval($_REQUEST['rat_action'.$i]);
			if(isset($_REQUEST['rat_min'.$i]))
			        $arr["min"] = intval($_REQUEST['rat_min'.$i]);
			if(isset($_REQUEST['rat_max'.$i]))
			        $arr["max"] = intval($_REQUEST['rat_max'.$i]);
			if(isset($_REQUEST['rat_upload'.$i]))
			        $arr["upload"] = intval($_REQUEST['rat_upload'.$i]);
			if(isset($_REQUEST['rat_name'.$i]))
			{
			        $v = trim($_REQUEST['rat_name'.$i]);
			        if($v!='')
					$arr["name"] = $v;
			}
			$this->rat[] = $arr;
		}
                $this->store();
		$this->flush();
	}
	public function get()
	{
		$ret = "utWebUI.ratios = [";
		foreach($this->rat as $item)
			$ret.="{ action: ".$item["action"].", min: ".$item["min"].", max: ".$item["max"].", upload: ".$item["upload"].", name : ".quoteAndDeslashEachItem($item["name"])." },";
		$len = strlen($ret);
		if($ret[$len-1]==',')
			$ret = substr($ret,0,$len-1);
		return($ret."];\nutWebUI.maxRatio = ".MAX_RATIO.";\n");
	}
}

?>