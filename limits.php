<?php

require_once( dirname(__FILE__)."/../../php/xmlrpc.php" );
require_once( dirname(__FILE__)."/../../php/Snoopy.class.inc");
require_once( dirname(__FILE__)."/../../php/cache.php");
require_once( dirname(__FILE__)."/../../php/settings.php");
eval( FileUtil::getPluginConf('plimits') );

@define('MAX_SPEED', 10, true);

class speedLimit
{
	protected function collect()
	{
		$toCorrect = array();
		$req = new rXMLRPCRequest(
			new rXMLRPCCommand( "d.multicall", array(
			        "default",
				getCmd("d.get_hash="),
				getCmd("d.get_throttle_name="),
				getCmd('cat').'=$'.getCmd("get_throttle_up_max").'=$'.getCmd("d.get_throttle_name="),
				getCmd('cat').'=$'.getCmd("get_throttle_down_max").'=$'.getCmd("d.get_throttle_name=")))
			);
		if($req->success())
		{
			for($i=0; $i<count($req->val); $i+=4)
			{
				if($req->val[$i+1]=="slimit")
				{
					if(($req->val[$i+2]==="-1") && ($req->val[$i+3]==="-1"))
					{
						$toCorrect[] = $req->val[$i];
					}
				}
        		}
		}
		return($toCorrect);
	}

	protected function correct( $toCorrect )
	{
		$req = new rXMLRPCRequest();
		foreach($toCorrect as $hash)
		{
			$req->addCommand(new rXMLRPCCommand( "branch", array
			(
				$hash,
				getCmd("d.is_active="),
				getCmd('cat').'=$'.getCmd("d.stop").'=,$'.getCmd("d.set_throttle_name=").'slimit,$'.getCmd('d.start='),
				getCmd('d.set_throttle_name=').'slimit'
			)));
		}
		if($req->getCommandsCount())
			return($req->success());
		return(true);
	}

	public function check( $req, $hash, $name, $public )
	{
		if( ($name=='slimit') && !$public )
		{
			trackersLimit::trace('Remove throttle restriction from '.$hash);
			$req->addCommand(new rXMLRPCCommand( "branch", array
			(
				$hash,
				getCmd("d.is_active="),
				getCmd('cat').'=$'.getCmd("d.stop").'=,$'.getCmd("d.set_throttle_name=").',$'.getCmd('d.start='),
				getCmd('d.set_throttle_name=')
			)));
		}
		else
		if( ($name!='slimit') && $public )
		{
			trackersLimit::trace('Add throttle restriction to '.$hash);
			$req->addCommand(new rXMLRPCCommand( "branch", array
			(
				$hash,
				getCmd("d.is_active="),
				getCmd('cat').'=$'.getCmd("d.stop").'=,$'.getCmd("d.set_throttle_name=").'slimit,$'.getCmd('d.start='),
				getCmd('d.set_throttle_name=').'slimit'
			)));
		}
	}

	public function init()
	{
		global $MAX_UL_LIMIT;
		global $MAX_DL_LIMIT;
		$req = new rXMLRPCRequest( array(
			new rXMLRPCCommand( "get_upload_rate" ),
			new rXMLRPCCommand( "get_download_rate" ) ));
		if($req->success())
		{
			$req1 = new rXMLRPCRequest();
			if($req->val[0]==0)
				$req1->addCommand(new rXMLRPCCommand( "set_upload_rate", MAX_SPEED ));
			if($req->val[1]==0)
				$req1->addCommand(new rXMLRPCCommand( "set_download_rate", MAX_SPEED ));
			if(!$req1->getCommandsCount() || $req1->success())
			{
				$toCorrect = $this->collect();
				$req = new rXMLRPCRequest( array(
					new rXMLRPCCommand("throttle_up", array("slimit",$MAX_UL_LIMIT."")),
					new rXMLRPCCommand("throttle_down", array("slimit",$MAX_DL_LIMIT.""))
					)
				);
				return($req->success() && $this->correct( $toCorrect ));
			}
		}
		return(false);
	}
}

class ratioLimit
{
	protected function correct()
	{
		$cmd = new rXMLRPCCommand("d.multicall",array("default",getCmd("d.get_hash=")));
		$cmd->addParameters( array( getCmd("d.views.has")."=rlimit", getCmd("view.set_not_visible")."=rlimit" ) );
		$req = new rXMLRPCRequest($cmd);
		$req->setParseByTypes();
		if($req->success())
		{
			$req1 = new rXMLRPCRequest();
			foreach($req->strings as $no=>$hash)
			{
				if($req->i8s[$no*2]==1)
					$req1->addCommand(new rXMLRPCCommand("view.set_visible",array($hash,"rlimit")));
			}
			return(($req1->getCommandsCount()==0) || ($req1->success()));
		}
		return(false);
	}

	protected function flush()
	{
		global $RATIO_LIMIT;
		$req1 = new rXMLRPCRequest(new rXMLRPCCommand("view_list"));
		if($req1->success())
		{
			$req = new rXMLRPCRequest();
			if(!in_array("rlimit",$req1->val))
				$req->addCommand(new rXMLRPCCommand("group.insert_persistent_view", array("", "rlimit")));
			$req->addCommand(new rXMLRPCCommand("group.rlimit.ratio.enable",array("")));
			$req->addCommand(new rXMLRPCCommand("group.rlimit.ratio.min.set",$RATIO_LIMIT));
			$req->addCommand(new rXMLRPCCommand("group.rlimit.ratio.max.set",$RATIO_LIMIT));
			$req->addCommand(new rXMLRPCCommand("group.rlimit.ratio.upload.set",0));
			$req->addCommand(new rXMLRPCCommand("system.method.set", array("group.rlimit.ratio.command", getCmd("d.stop=")."; ".getCmd("d.close="))));
			return($req->success());
		}
		return(false);
	}

	public function check( $req, $hash, $present, $public )
	{
		if( $present && !$public )
		{
			trackersLimit::trace('Remove ratio restriction from '.$hash);
			$req->addCommand(new rXMLRPCCommand("view.set_not_visible",array($hash, 'rlimit')));
			$req->addCommand(new rXMLRPCCommand("d.views.remove",array($hash, 'rlimit')));
		}
		else
		if( !$present && $public )
		{
			trackersLimit::trace('Add ratio restriction to '.$hash);
			$req->addCommand(new rXMLRPCCommand("d.views.push_back_unique",array($hash, 'rlimit')));
			$req->addCommand(new rXMLRPCCommand("view.set_visible",array($hash, 'rlimit')));
		}
	}

	public function init()
	{
        	return($this->flush() && $this->correct());
	}
}

class trackersLimit
{
	protected $sl;
	protected $rl;
	protected $trackers = array();

	public function init()
	{
		global $trackersCheckInterval;
		global $preventUpload;

		$this->sl = new speedLimit();
		$this->rl = new ratioLimit();

		if( $this->sl->init() && $this->rl->init() )
		{
			$req = new rXMLRPCRequest(
				rTorrentSettings::get()->getOnInsertCommand( array('_plimits'.User::getUser(),
					getCmd('execute.nothrow').'={'.Utility::getPHP().','.dirname(__FILE__).'/update.php,"$'.
					getCmd('t.multicall').'=$'.getCmd('d.get_hash').'=,'.getCmd('t.get_url').'=,'.getCmd('cat').'=#",$'.getCmd('d.get_hash').'=,insert,'.User::getLogin().'}' ) ) );
			if($preventUpload)
			{
				$req->addCommand(
					rTorrentSettings::get()->getOnFinishedCommand(array('_plimits1'.User::getUser(),
					getCmd('execute.nothrow').'={'.Utility::getPHP().','.dirname(__FILE__).'/update.php,"$'.
					getCmd('t.multicall').'=$'.getCmd('d.get_hash').'=,'.getCmd('t.get_url').'=,'.getCmd('cat').'=#",$'.getCmd('d.get_hash').'=,finish,'.User::getLogin().'}' ) ) );
				$req->addCommand(
					rTorrentSettings::get()->getOnResumedCommand(array('_plimits2'.User::getUser(),
					getCmd('execute.nothrow').'={'.Utility::getPHP().','.dirname(__FILE__).'/update.php,"$'.
					getCmd('t.multicall').'=$'.getCmd('d.get_hash').'=,'.getCmd('t.get_url').'=,'.getCmd('cat').'=#",$'.getCmd('d.get_hash').'=,resume,'.User::getLogin().'}' ) ) );
			}
			if($req->success())
			{
				$this->check();
				$req = new rXMLRPCRequest( rTorrentSettings::get()->getScheduleCommand('plimits',$trackersCheckInterval,
					getCmd('execute').'={sh,-c,'.escapeshellarg(Utility::getPHP()).' '.escapeshellarg(dirname(__FILE__).'/check.php').' '.escapeshellarg(User::getUser()).' &}' ) );
				return( $req->success() );
			}
		}
		return(false);
	}

	public function loadLocal()
	{
		$this->trackers = array();
		$fname = FileUtil::getSettingsPath()."/trackers.lst";
		if(!is_readable($fname))
			$fname = dirname(__FILE__)."/trackers.lst";
		$results = file_get_contents($fname);
		if($results!==false)
		{
			$this->trackers = explode("\n", $results);
			return(true);
		}
		return(false);
	}

	public function load()
	{
		global $profileMask;
		$fname = FileUtil::getSettingsPath()."/trackers.lst";
		if(!is_readable($fname))
			$fname = dirname(__FILE__)."/trackers.lst";
		$ftime = filemtime($fname);
		$client = new Snoopy();
		$this->trackers = array();
		if($ftime!==false)
			$client->rawheaders['If-Modified-Since'] = gmdate('D, d M Y H:i:s T', $ftime);
		$this->loadLocal();
		return(false);
	}

	public function checkPublic( $trackers )
	{
		global $enableOnDHT;
		if( $enableOnDHT && strpos( $trackers, "dht://" )!==false )
			return(true);
		foreach( $this->trackers as $trk )
			if( ! empty( $trk ) && stristr( $trackers, $trk )!==false )
				return(true);
		return(false);
	}

	public function check()
	{
		if($this->loadLocal())
		{
			$req =  new rXMLRPCRequest(
				new rXMLRPCCommand("d.multicall",array("default",
					getCmd("d.get_hash="),
					getCmd("d.get_throttle_name="),
					getCmd("d.views.has")."=rlimit",
					getCmd("cat").'="$'.getCmd("t.multicall=").getCmd("d.get_hash=").",".getCmd("t.get_url")."=,".getCmd("cat=#").'"'
				)));
			if($req->success())
			{
				$req1 = new rXMLRPCRequest();
				for($i=0; $i<count($req->val); $i+=4)
				{
					$public = $this->checkPublic($req->val[$i+3]);
					self::trace($req->val[$i].' is '.($public ? 'public' : 'private').' throttle '.$req->val[$i+1].' hasratio '.$req->val[$i+2]);
					$this->sl->check( $req1, $req->val[$i], $req->val[$i+1], $public );
					$this->rl->check( $req1, $req->val[$i], ($req->val[$i+2]==1), $public );
				}
				return(!$req1->getCommandsCount() || $req1->success());
			}
		}
		return(false);
	}

	static public function trace( $msg, $err = false )
	{
		global $log_debug;
		if($log_debug)
		{
			FileUtil::toLog( $msg );
			if($err)
			{
				$dbg = error_get_last();
				if($dbg)
					FileUtil::toLog(print_r($dbg,true));
			}
		}
	}
}
