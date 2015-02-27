<?php
/**
 * AutoloadCommand class file.
 */

/**
 * AutoloadCommand generates the class map for {@link Mod}.
 * The class file Mod.php will be modified with updated class map.
 */
class AutoloadCommand extends CConsoleCommand
{
	public function getHelp()
	{
		return <<<EOD
USAGE
  modc autoload

DESCRIPTION
  This command updates Mod.php with the latest class map.
  The class map is used by Mod::autoload() to quickly include a class on demand.

  Do not run this command unless you change or add core framework classes.

EOD;
	}

	/**
	 * Execute the action.
	 * @param array command line parameters specific for this command
	 */
	public function run($args)
	{
		$options=array(
			'fileTypes'=>array('php'),
			'exclude'=>array(
				'.gitignore',
				'.svn',
				'/messages',
				'/views',
				'/cli',
				'/web/js',
				'/vendors',
				'/i18n/data',
				'/utils/mimeTypes.php',
				'/exts/zii',
				'/generator',
				'/components/queue/pheanstalk',
				'/counter',
				'/generator',
				'base.php',
				'basewb.php'
			),
		);
		$files=CFileHelper::findFiles(MOD_PATH,$options);
		$map='';
		foreach($files as $file)
		{
			if(($pos=strpos($file,MOD_PATH))!==0)
				die("Invalid file '$file' found.");
			$path=str_replace('\\','/',substr($file,strlen(MOD_PATH)));
			$className=substr(basename($path),0,-4);
			if($className[0]==='C')
				$map.="\t\t'$className'=>'$path',\n";
		}
		
		$ModBase=file_get_contents(MOD_PATH.'/Mod.php');
		$newModBase=preg_replace('/public\s+static\s+\$coreClasses\s*=\s*array\s*\([^\)]*\)\s*;/',"public static \$coreClasses=array(\n{$map}\t);",$ModBase);
		if($ModBase!==$newModBase)
		{
			file_put_contents(MOD_PATH.'/Mod.php',$newModBase);
			echo "Mod.php is updated successfully.\n";
		}
		else
			echo "Nothing changed.\n";
	}
}