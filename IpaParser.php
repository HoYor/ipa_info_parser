<?php
/*
 * @Author: hr
 * @Date: 2020-05-28 11:00:21
 * @LastEditors: hr
 * @LastEditTime: 2020-05-29 12:40:37
 * @Description: description
 */ 

namespace CFPropertyList;

use Stnvh\Partial\Zip;

class IpaParser {
	// 读取本地IPA的信息
	public static function readLocalIpaInfo(string $projectDir, string $ipaPath){
		$ipaRealPath = $projectDir.'/public/upload/'.$ipaPath;
        if (!file_exists($ipaRealPath) || !is_file($ipaRealPath)) {
            throw new \Exception('安装包不存在');
		}
		
		$zip = new Zip(false, $ipaRealPath);
		return readIpaInfo($projectDir, $zip, md5_file($ipaRealPath));
	}
	
	// 读取远程IPA的信息
	public static function readRemoteIpaInfo(string $projectDir, string $ipaUrl){
		$zip = new Zip($ipaUrl);
		return readIpaInfo($projectDir, $zip);
	}

	// 读取IPA信息
	private static function readIpaInfo(string $projectDir, Zip $zip, $fileMd5 = ''){
		if($file_array = $zip->search('.app/Info.plist')) {
			if(count($file_array) > 0){
				// echo 'parse plist file:'.$file_array[0].PHP_EOL;
				$content = $zip->get($zip->find($file_array[0]));
				// echo 'parse plist file result:'.PHP_EOL.$content.PHP_EOL;
				$temp_plist_path = tempnam(sys_get_temp_dir(), 'PLIST~');
				file_put_contents($temp_plist_path, $content);
				$plist = new CFPropertyList( $temp_plist_path, CFPropertyList::FORMAT_XML );
				$plist_value = $plist->toArray();
				$ios_app_info['uri'] = $zip->getUrl();
				
				if(isset($plist_value['CFBundleIcons']['CFBundlePrimaryIcon']['CFBundleIconFiles'])){
					$icon_files = $plist_value['CFBundleIcons']['CFBundlePrimaryIcon']['CFBundleIconFiles'];
					if(count($icon_files) > 0){
						$icon = $icon_files[count($icon_files)-1];
						if($icon_array = $zip->search($icon)){
							if(count($icon_array) > 0){
								$max_icon = $icon_array[count($icon_array)-1];
								echo 'max_icon:'.$max_icon.PHP_EOL;
								$iconContent = $zip->get($zip->find($max_icon));
								$md5 = md5($iconContent);
								$dir = 'attachment/image/'.substr($md5, 0, 2).'/'.substr($md5, 2, 2).'/';
								$directory = $projectDir.'/public/upload/'.$dir;
	
								if (!is_dir($directory) && !mkdir($directory, 0777, true)) {
									throw new \Exception('保存图标目录创建失败');
								}
	
								$iconExt = strtolower(pathinfo($max_icon, PATHINFO_EXTENSION));
								if (in_array($iconExt, ['png', 'jpeg', 'jpg'])) {
									if (file_put_contents($directory.$md5.'.'.$iconExt, $iconContent)) {
										$ios_app_info['icon'] = $dir.$md5.'.'.$iconExt;
									}
								}
							}
						}
					}
				}
				if (empty($ios_app_info['icon'])) {
					throw new \Exception('提取应用图标失败');
				}
		
				$ios_app_info['title'] = $plist_value['CFBundleName'];
				if(empty($ios_app_info['title'])){
					throw new \Exception('读取应用名称失败');
				}
				$ios_app_info['name'] = $plist_value['CFBundleIdentifier'];
				if(empty($ios_app_info['name'])){
					throw new \Exception('读取应用包名失败');
				}
				// $ios_app_info['min_sdk_version'] = $plist_value['MinimumOSVersion'];
				// $ios_app_info['target_sdk_version'] = $plist_value['DTPlatformVersion'];
				$ios_app_info['size'] = $zip->getContentLength();
				$ios_app_info['md5'] = $fileMd5;
				$app_project = $_ENV['APP_PROJECT'] ?? 'aiwanaiwan';
				if(isset($plist_value['com.'.$app_project.'.apptype'])){
					$ios_app_info['appType'] = $plist_value['com.'.$app_project.'.apptype'];
				}else{
					$ios_app_info['appType'] = 'unknown';
				}
				$ios_app_info['appId'] = (int) $plist_value['com.'.$app_project.'.appid'];
				$ios_app_info['appKey'] = $plist_value['com.'.$app_project.'.appkey'];
				if(isset($plist_value['com.'.$app_project.'.sdk.versioncode'])){
					$ios_app_info['version_code'] = $plist_value['com.'.$app_project.'.sdk.versioncode'];
				}else{
					$ios_app_info['version_code'] = $plist_value['CFBundleVersion'];
				}
				if(isset($plist_value['com.'.$app_project.'.sdk.versionname'])){
					$ios_app_info['version_code'] = $plist_value['com.'.$app_project.'.sdk.versionname'];
				}else{
					$ios_app_info['version_name'] = $plist_value['CFBundleShortVersionString'];
				}
				return $ios_app_info;
			}
		}
	}
}

?>