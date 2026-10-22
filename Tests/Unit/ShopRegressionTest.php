<?php
declare(strict_types=1);

// Rendus stricts et requêtes de lecture : aucune fixture ni donnée métier écrite.
$root=dirname(__DIR__,2);
set_error_handler(static function(int $severity,string $message,string $file,int $line):bool {
    if(!(error_reporting() & $severity))return false;
    throw new ErrorException($message,0,$severity,$file,$line);
});
spl_autoload_register(static function(string $class)use($root):void{
    if(!str_starts_with($class,'App\\'))return;
    $parts=explode('\\',substr($class,4));$parts[0]=strtolower($parts[0]);
    $file=$root.'/App/'.implode('/',$parts).'.php';if(is_file($file))require_once $file;
});
if(in_array($argv[1]??'', ['page','api'],true)) {
    $_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']=$argv[1]==='page'?'/boutique':'/api/catalogue';
    parse_str($argv[2]??'',$_GET);http_response_code(200);
    require $root.'/Public/index.php';echo "\n__STATUS=".http_response_code();exit;
}
$assert=static function(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);};
$render=static function(array $config)use($root):string{
    $catalogFilters=$config;ob_start();require $root.'/App/views/components/catalog-filters.php';return ob_get_clean();
};
foreach([[],['filters'=>['rating'=>5]],['filters'=>['categories'=>['test']]],['filters'=>['statuses'=>['promotion']]],['filters'=>['availability'=>'in-stock']]] as $config) {
    $html=$render($config);
    $assert(str_starts_with(ltrim($html),'<aside'),'Aucun texte/Warning avant le panneau de grille');
    $assert(!str_contains($html,'name="rating"')&&!str_contains($html,'Note minimale'),'Notation absente');
    $assert(str_contains($html,'Catégorie')&&str_contains($html,'Offres')&&str_contains($html,'Disponibilité'),'Filtres utiles conservés');
}
$run=static function(string $mode,array $query=[])use($assert):string{
    $process=proc_open([PHP_BINARY,__FILE__,$mode,http_build_query($query)],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    $assert(proc_close($process)===0&&$err===''&&str_ends_with($out,'__STATUS=200'),'Route '.$mode.' : '.$err);
    return substr($out,0,strrpos($out,"\n__STATUS="));
};
$page=$run('page');
$assert(!preg_match('/Undefined variable|PHP Warning|PHP Notice/',$page),'Page boutique sans avertissement');
$assert(str_contains($page,'/assets/css/select-control.css') && strpos($page,'/assets/js/select-control.js')!==false && strpos($page,'/assets/js/select-control.js')<strpos($page,'/assets/js/shop.js'),'Composant de tri partagé chargé avant shop.js');
$previous=libxml_use_internal_errors(true);$dom=new DOMDocument();$dom->loadHTML('<?xml encoding="UTF-8">'.$page);libxml_clear_errors();libxml_use_internal_errors($previous);
$xpath=new DOMXPath($dom);$layout=$xpath->query('//*[@data-product-catalog]')->item(0);
$assert($xpath->query('//label[@for="catalog-sort-select"]//span[@data-select-label]')->length===1 && $xpath->query('//select[@id="catalog-sort-select"]')->length===1,'Tri compatible avec le composant commun, vrai select conservé');
$assert($layout!==null,'Layout catalogue présent');$elements=[];
foreach($layout->childNodes as $child) {
    if($child->nodeType===XML_TEXT_NODE)$assert(trim($child->textContent)==='','Pas de texte créant une cellule de grille anonyme');
    if($child instanceof DOMElement)$elements[]=$child;
}
$assert(count($elements)===3&&$elements[0]->tagName==='aside'&&$elements[1]->tagName==='button'&&$elements[2]->hasAttribute('data-catalog-results'),'Panneau, overlay et résultats dans leur ordre prévu');
$footer=$xpath->query('//footer//a[@href="/#footer-about"]');$assert($footer->length===1,'À propos conservé dans le footer vers la destination existante');
$mobile=$xpath->query('//nav[@id="primary-navigation"]//a[@href="/#footer-about"]/parent::li')->item(0);
$assert($mobile!==null&&str_contains($mobile->getAttribute('class'),'site-navigation__mobile-action'),'À propos seulement dans le menu mobile');
$desktopLabels=[];
foreach($xpath->query('//nav[@id="primary-navigation"]/ul[1]/li[not(contains(@class,"site-navigation__mobile-action"))]') as $item)$desktopLabels[]=trim(preg_replace('/\s+/u',' ',$item->textContent));
$assert($desktopLabels===['Accueil','Catégories','Boutique','Promotions'],'Navigation desktop limitée aux quatre entrées attendues');
$category=$xpath->query('//input[@name="categories[]"]')->item(0)?->getAttribute('value');
$cases=[[],['availability'=>'in-stock'],['availability'=>'out-of-stock'],['statuses'=>['promotion']],['statuses'=>['new']],['statuses'=>['limited']],['sort'=>'price-asc'],['sort'=>'price-desc']];
if($category!==null)$cases[]=['categories'=>[$category],'availability'=>'in-stock','sort'=>'price-asc'];
foreach($cases as $query) {
    $payload=json_decode($run('api',$query),true,512,JSON_THROW_ON_ERROR);
    $assert(is_int($payload['count'])&&is_string($payload['html']),'Catalogue dynamique lisible');
    $assert(!str_contains($payload['html'],'product-card__rating')&&!str_contains($payload['html'],'data-lucide="star"'),'Avis non réintroduits dans les cartes dynamiques');
}
echo "SHOP_REGRESSION_OK (rendu strict, grille sans cellule anonyme, catégories/offres/stock/tri API, navigation/footer, avis absents, lecture seule).\n";
