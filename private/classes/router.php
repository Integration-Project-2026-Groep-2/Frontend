<?php
	class Router{
		private static array $list;
		private static $url = null;
		private static $pagefolder = __DIR__.'/../pages';

		static function Init($path){
			self::$pagefolder=$path;
		}

		private static function AddList($name,$path){
			if (!isset(self::$list[$name])) {
				self::$list[$name]=$path;
			}
		}

		static function Default($url="/home"){
			if (parse_url(self::GetUrl(), PHP_URL_PATH)=="/") {
				header("Location: $url");
			}
		}
		
		static function Add($aliases,$path){
			if (is_string($aliases)) {
				self::AddList($aliases,$path);

			} elseif (is_array($aliases)) {
				foreach ($aliases as $alias) {
					self::AddList($alias,$path);
				}
			}	
		}

		static function Bypass($url){
			if (is_string($url) and $_SERVER['REQUEST_URI'] == $url) {
				readfile(__DIR__ . $url);
				exit;
			}
		}

		static function Finish(){
			$url = self::GetUrl();
			
			if (isset(self::$list[parse_url($url, PHP_URL_PATH)])) {
				$result=self::$list[parse_url($url, PHP_URL_PATH)];
				
				if (is_file(self::$pagefolder.$result)) {
					include self::$pagefolder.$result;
				} else {
					$error="url_routed_but_not_found";
					include self::$pagefolder.'/errorpages/404.php';
				}
			} else{
				$error="url_not_routed";
				include self::$pagefolder.'/errorpages/404.php';
			}
		}

		static function GetUrl(){
			if (self::$url==null) {
				self::$url=strtolower('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
			}
			return self::$url;
		}
	}