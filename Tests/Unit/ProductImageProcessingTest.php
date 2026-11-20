<?php
declare(strict_types=1);

// Doubles limités aux frontières GD : les appels normaux passent réellement par GD.
// Ils prouvent le refus AVANT décodage et simulent un échec après un premier WebP.
namespace App\Services {
    function imagecreatefromjpeg(string $file): \GdImage|false {
        $GLOBALS['image_test_decodes'] = ($GLOBALS['image_test_decodes'] ?? 0) + 1;
        return \imagecreatefromjpeg($file);
    }
    function imagecreatefrompng(string $file): \GdImage|false {
        $GLOBALS['image_test_decodes'] = ($GLOBALS['image_test_decodes'] ?? 0) + 1;
        return \imagecreatefrompng($file);
    }
    function imagewebp(\GdImage $image, string $file, int $quality): bool {
        $GLOBALS['image_test_last_quality']=$quality;
        $calls = $GLOBALS['image_test_encodes'] = ($GLOBALS['image_test_encodes'] ?? 0) + 1;
        if (($GLOBALS['image_test_fail_at'] ?? 0) === $calls) return false;
        if (($GLOBALS['image_test_empty_at'] ?? 0) === $calls) return true;
        return \imagewebp($image, $file, $quality);
    }
    function mkdir(string $directory, int $permissions): bool {
        if ($GLOBALS['image_test_directory_collision'] ?? false) {
            \mkdir($directory,$permissions);
            $GLOBALS['image_test_foreign_directory']=$directory;
            return false;
        }
        return \mkdir($directory,$permissions);
    }
}

namespace {
    require_once dirname(__DIR__, 2) . '/App/services/UploadService.php';
    use App\Services\UploadService;

    $assert = static function(bool $ok, string $label): void {
        if (!$ok) throw new RuntimeException($label);
    };
    // Suppression uniquement dans notre dossier aléatoire, jamais dans Public.
    $clean = static function(string $directory): void {
        $resolved = realpath($directory);
        $temporary = realpath(sys_get_temp_dir());
        if ($resolved === false) return;
        if ($temporary === false || !str_starts_with(strtolower(str_replace('\\','/',$resolved)),
            strtolower(rtrim(str_replace('\\','/',$temporary),'/')).'/tye-pipeline-test-')) throw new RuntimeException('Nettoyage hors dossier de test refusé');
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isLink()) throw new RuntimeException('Lien inattendu dans les fixtures');
            if ($file->isDir()) {if (!rmdir($file->getPathname())) throw new RuntimeException('Nettoyage dossier échoué');}
            elseif (!unlink($file->getPathname())) throw new RuntimeException('Nettoyage fichier échoué');
        }
        if (!rmdir($resolved)) throw new RuntimeException('Nettoyage racine échoué');
    };
    $removeResult = static function(array $result): void {
        foreach (array_unique(array_column($result['derivatives'], 'path')) as $file) {
            if (dirname($file) !== $result['temporary_directory']) throw new RuntimeException('Chemin de sortie inattendu');
            if (!unlink($file)) throw new RuntimeException('Suppression du dérivé de test échouée');
        }
        if (!rmdir($result['temporary_directory'])) throw new RuntimeException('Suppression du dossier dérivé échouée');
    };
    if (($argv[1] ?? '') === 'measure') {
        $directory = realpath($argv[3]);
        if ($directory === false || !preg_match('/^tye-pipeline-test-[a-f0-9]{16}$/D', basename($directory))
            || dirname(realpath($argv[2]) ?: '') !== $directory) throw new RuntimeException('Fixture worker refusée');
        $service = new UploadService(['temporary_root' => $directory]);
        if (function_exists('memory_reset_peak_usage')) memory_reset_peak_usage();
        $baseline = memory_get_usage(true);
        $result = $service->process($argv[2]);
        $peak = memory_get_peak_usage(true);
        echo json_encode(['dimensions' => [$result['source']['width'], $result['source']['height']],
            'orientation' => $result['source']['orientation'], 'orientedDimensions' => [$result['width'],$result['height']],
            'pixels' => $result['source']['width'] * $result['source']['height'], 'sourceBytes' => $result['source']['bytes'],
            'derivativeBytes' => array_map(static fn(array $d): int => $d['bytes'], $result['derivatives']),
            'phpBaselineBytes' => $baseline, 'phpPeakBytes' => $peak, 'memoryLimit' => ini_get('memory_limit')]), PHP_EOL;
        $removeResult($result);
        exit;
    }
    if (($argv[1] ?? '') === 'boundary') {
        $directory=realpath($argv[3]);$file=realpath($argv[2]);
        if($directory===false||$file===false||dirname($file)!==$directory
            ||!preg_match('/^tye-pipeline-test-[a-f0-9]{16}$/D',basename($directory)))throw new RuntimeException('Fixture frontière refusée');
        $engine=new UploadService(['temporary_root'=>$directory]);
        $before=$GLOBALS['image_test_decodes']??0;
        $originalLimit=ini_get('memory_limit');
        if(($argv[4]??'')==='reject') {
            try {$engine->process($file);throw new RuntimeException('Budget PHP insuffisant accepté');}
            catch(DomainException $error) {$assert(str_contains($error->getMessage(),'sécurité'),'Refus mémoire attendu');}
            $assert(($GLOBALS['image_test_decodes']??0)===$before,'Refus 24 MP avant GD avec PHP 128M');
            $assert(glob($directory.'/tye-image-*')===[],'Refus sans résidu');
        } else {
            $result=$engine->process($file);
            $assert($result['source']['width']===4000&&$result['source']['height']===6000,'Exactement 24 MP et côté 6000 acceptés');
            foreach(['thumb'=>[200,300],'medium'=>[533,800],'large'=>[1067,1600]]as$key=>$expected) {
                $output=imagecreatefromwebp($result['derivatives'][$key]['path']);
                $assert($output instanceof GdImage&&[imagesx($output),imagesy($output)]===$expected,'Frontière décodée : '.$key);unset($output);
            }
            $removeResult($result);
        }
        $assert(ini_get('memory_limit')===$originalLimit,'Le moteur ne relève jamais la limite PHP');
        echo 'IMAGE_BOUNDARY_OK ',($argv[4]??'accept'),' PHP=',$originalLimit,PHP_EOL;
        exit;
    }

    foreach (['gd','fileinfo','exif'] as $extension) $assert(extension_loaded($extension), 'Extension requise : '.$extension);
    $root = sys_get_temp_dir() . '/tye-pipeline-test-' . bin2hex(random_bytes(8));
    if (!mkdir($root,0700)) throw new RuntimeException('Création fixtures impossible');
    $originalMemoryLimit = ini_get('memory_limit');
    $originalJpegWarnings = ini_get('gd.jpeg_ignore_warning');
    $sources = [];
    $fixture = static function(string $name, int $width, int $height, string $format = 'jpeg', bool $alpha = false) use ($root, &$sources): string {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false); imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, $alpha ? 127 : 0));
        // Motif déterministe non photographique, sans hasard ni dépendance commerciale.
        for ($i = 0; $i < 16; $i++) {
            $color = imagecolorallocatealpha($image, ($i * 17) % 256, 255 - $i * 13, 40 + $i * 9, 0);
            imagefilledrectangle($image, (int)floor($width*$i/16), (int)floor($height/3),
                max(0,(int)ceil($width*($i+1)/16)-1), $height-1, $color);
        }
        $file = $root . '/' . $name;
        $ok = match ($format) {'jpeg'=>imagejpeg($image,$file,90),'png'=>imagepng($image,$file),'webp'=>imagewebp($image,$file,90)};
        if (!$ok) throw new RuntimeException('Fixture non créée');
        unset($image);
        $sources[$file] = hash_file('sha256',$file);
        return $file;
    };
    $service = new UploadService(['temporary_root'=>$root]);
    $reject = static function(string $file, ?UploadService $engine = null, bool $beforeDecode = false) use ($service,$assert,$root): void {
        $before = glob($root.'/tye-image-*');
        $decodes = $GLOBALS['image_test_decodes'] ?? 0;
        try {($engine ?? $service)->process($file);throw new RuntimeException('Fichier refusé attendu');}
        catch (DomainException $error) {
            $assert(in_array($error->getCode(),[422,503],true), 'Erreur contrôlée');
            $assert(!str_contains($error->getMessage(),$root), 'Aucun chemin système dans le message');
        }
        $assert(glob($root.'/tye-image-*') === $before, 'Aucun résidu après échec');
        if ($beforeDecode) $assert(($GLOBALS['image_test_decodes'] ?? 0) === $decodes, 'Refus avant décodage GD');
    };
    $verify = static function(array $result, array $dimensions) use ($assert): void {
        foreach (['thumb','medium','large'] as $index=>$size) {
            $derivative = $result['derivatives'][$size];
            $assert([$derivative['width'],$derivative['height']] === $dimensions[$index], 'Dimensions '.$size);
            $assert($derivative['bytes'] > 0 && $derivative['bytes'] === filesize($derivative['path']), 'Poids réel '.$size);
            $assert((new finfo(FILEINFO_MIME_TYPE))->file($derivative['path']) === 'image/webp', 'MIME WebP réel');
            $image = imagecreatefromwebp($derivative['path']);
            $assert($image instanceof GdImage && [imagesx($image),imagesy($image)] === $dimensions[$index], 'Relecture réelle '.$size);
            $assert((bool)preg_match('/^[a-f0-9]{32}\.webp$/D',basename($derivative['path'])), 'Nom opaque');
            unset($image);
        }
    };
    try {
        $policy=require dirname(__DIR__,2).'/Config/upload.php';
        $assert($policy['max_pixels']===24_000_000&&$policy['max_dimension']===6000&&$policy['memory_budget_bytes']===448*1024*1024,'Politique téléphone V1');
        $landscape = $fixture('landscape.client-name.jpg',1600,1200);
        $portrait = $fixture('portrait.jpg',1200,1600);
        $square = $fixture('square.jpg',1600,1600);
        $small = $fixture('small.jpg',200,150);
        $large = $fixture('four-megapixels.jpg',2400,1600);
        $five = $fixture('five-megapixels.jpg',2500,2000);
        foreach ([[$landscape,[[300,225],[800,600],[1600,1200]]],
            [$portrait,[[225,300],[600,800],[1200,1600]]],
            [$square,[[300,300],[800,800],[1600,1600]]],
            [$small,[[200,150],[200,150],[200,150]]],
            [$large,[[300,200],[800,533],[1600,1067]]],
            [$five,[[300,240],[800,640],[1600,1280]]]] as [$file,$dimensions]) {
            // Les allocations des générateurs de fixtures ne font pas partie d'un upload.
            gc_collect_cycles();gc_mem_caches();
            $result = $service->process($file);
            $verify($result,$dimensions);
            $assert($result['source']['mime']==='image/jpeg','JPEG réel accepté');
            if ($file===$small) $assert(count(array_unique(array_column($result['derivatives'],'path')))===1,'Pas de copies identiques ni upscale');
            $removeResult($result);
        }
        $renamed = $fixture('valid-image.without-image-extension',200,150);
        $result=$service->process($renamed);$assert($result['source']['mime']==='image/jpeg','Décodeur choisi par contenu, pas extension');$removeResult($result);
        $assert($GLOBALS['image_test_last_quality']===82,'Qualité centrale par défaut');
        $result=(new UploadService(['temporary_root'=>$root,'webp_quality'=>37]))->process($small);
        $assert($GLOBALS['image_test_last_quality']===37,'Qualité configurable transmise à GD');$removeResult($result);
        foreach (['png','webp'] as $format) {
            $file=$fixture('transparent.'.$format,640,480,$format,true);
            $result=$service->process($file);$verify($result,[[300,225],[640,480],[640,480]]);
            $assert($result['source']['mime']==='image/'.$format,'MIME source '.$format);
            $image=imagecreatefromwebp($result['derivatives']['thumb']['path']);
            $color=imagecolorsforindex($image,imagecolorat($image,5,5));
            $assert($color['alpha']>=120,'Transparence préservée, pas de fond noir : '.$format);
            unset($image);$removeResult($result);
        }

        file_put_contents($root.'/fake.jpg',"<?php echo 'not an image';");$reject($root.'/fake.jpg',null,true);
        file_put_contents($root.'/empty.jpg','');$reject($root.'/empty.jpg',null,true);
        file_put_contents($root.'/vector.svg','<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>');$reject($root.'/vector.svg',null,true);
        $gif=imagecreatetruecolor(10,10);imagegif($gif,$root.'/fake-gif.jpg');imagebmp($gif,$root.'/fake-bmp.jpg');unset($gif);
        $reject($root.'/fake-gif.jpg',null,true);$reject($root.'/fake-bmp.jpg',null,true);
        file_put_contents($root.'/corrupt.jpg',substr(file_get_contents($landscape),0,100));$reject($root.'/corrupt.jpg');
        file_put_contents($root.'/truncated.jpg',substr(file_get_contents($landscape),0,-1000));$reject($root.'/truncated.jpg');
        // En-tête PNG valide mais pixels absents : passe MIME/dimensions, échoue au vrai décodeur.
        file_put_contents($root.'/corrupt-pixels.png',substr(file_get_contents($root.'/transparent.png'),0,33));
        $decodes=$GLOBALS['image_test_decodes']??0;$reject($root.'/corrupt-pixels.png');
        $assert(($GLOBALS['image_test_decodes']??0)===$decodes+1,'Fichier corrompu effectivement refusé par GD');
        $handle=fopen($root.'/oversized.jpg','wb');ftruncate($handle,10*1024*1024+1);fclose($handle);$reject($root.'/oversized.jpg',null,true);
        $reject($root.'/missing.jpg',null,true);$reject($root,null,true);$reject('https://example.invalid/image.jpg',null,true);
        // En-têtes PNG synthétiques : aucune allocation d'un tampon géant pour tester la garde.
        $chunk=static fn(string $kind,string $data):string=>pack('N',strlen($data)).$kind.$data.pack('N',crc32($kind.$data));
        foreach (['pixels'=>[5000,4801],'side'=>[6001,1]] as $name=>[$width,$height]) {
            $file=$root.'/'.$name.'.png';
            file_put_contents($file,"\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',$width,$height,8,6,0,0,0)).$chunk('IEND',''));
            $assert(getimagesize($file)[0]===$width,'Fixture dimensions sans décodage');
            $reject($file,null,true);
        }
        $reject($landscape,new UploadService(['temporary_root'=>$root,'memory_budget_bytes'=>32*1024*1024]),true);
        // Même PHP 512M ne permet pas au moteur de dépasser son plafond applicatif.
        // En-tête de 23,4 MP : sous les plafonds pixels/côté, mais ~477 Mio estimés.
        $crowded=$root.'/application-budget.png';
        file_put_contents($crowded,"\x89PNG\r\n\x1a\n".$chunk('IHDR',pack('NNCCCCC',4800,4900,8,6,0,0,0)).$chunk('IEND',''));
        $held=str_repeat('m',72*1024*1024);
        try {$reject($crowded,null,true);}finally{unset($held);gc_collect_cycles();gc_mem_caches();}
        $result=(new UploadService(['temporary_root'=>$root,'max_source_bytes'=>filesize($small)]))->process($small);
        $removeResult($result);
        $reject($small,new UploadService(['temporary_root'=>$root,'max_source_bytes'=>filesize($small)-1]),true);
        try {new UploadService(['temporary_root'=>dirname(__DIR__,2).'/Public']);throw new RuntimeException('Stockage Public accepté');}
        catch(InvalidArgumentException) {}

        $GLOBALS['image_test_encodes']=0;$GLOBALS['image_test_fail_at']=2;
        $reject($landscape);$assert($GLOBALS['image_test_encodes']===2,'Échec après un dérivé réellement créé');
        unset($GLOBALS['image_test_fail_at']);
        $GLOBALS['image_test_encodes']=0;$GLOBALS['image_test_empty_at']=2;
        $reject($landscape);unset($GLOBALS['image_test_empty_at']);
        $GLOBALS['image_test_directory_collision']=true;
        try {$service->process($small);throw new RuntimeException('Collision attendue');}
        catch(DomainException) {$assert(is_dir($GLOBALS['image_test_foreign_directory']),'Ne supprime pas un dossier préexistant');}
        finally {unset($GLOBALS['image_test_directory_collision']);}
        rmdir($GLOBALS['image_test_foreign_directory']);unset($GLOBALS['image_test_foreign_directory']);

        // JPEG asymétrique 80x40, quatre couleurs ; EXIF injecté dans un vrai APP1/TIFF.
        $palette=[[240,20,20],[20,230,20],[20,20,240],[240,230,20]];
        $image=imagecreatetruecolor(80,40);
        foreach($palette as $index=>$rgb)imagefilledrectangle($image,($index%2)*40,intdiv($index,2)*20,($index%2)*40+39,intdiv($index,2)*20+19,imagecolorallocate($image,...$rgb));
        ob_start();imagejpeg($image,null,100);$jpeg=(string)ob_get_clean();unset($image);
        $corners=[1=>[0,1,2,3],2=>[1,0,3,2],3=>[3,2,1,0],4=>[2,3,0,1],
            5=>[0,2,1,3],6=>[2,0,3,1],7=>[3,1,2,0],8=>[1,3,0,2]];
        foreach($corners as $orientation=>$expected) {
            $exif="Exif\0\0II".pack('vVv',42,8,1).pack('vvV',0x0112,3,1).pack('v',$orientation)."\0\0".pack('V',0);
            $file=$root.'/orientation-'.$orientation.'.jpg';
            file_put_contents($file,substr($jpeg,0,2)."\xff\xe1".pack('n',strlen($exif)+2).$exif.substr($jpeg,2));
            $assert(exif_read_data($file,'IFD0',true)['IFD0']['Orientation']===$orientation,'EXIF réel présent : '.$orientation);
            $result=$service->process($file);
            $dims=$orientation>=5?[40,80]:[80,40];$verify($result,[$dims,$dims,$dims]);
            $assert($result['source']['orientation']===$orientation&&[$result['width'],$result['height']]===$dims,'Dimensions après orientation');
            $output=imagecreatefromwebp($result['derivatives']['thumb']['path']);
            foreach($expected as $position=>$index) {
                $x=(int)round($dims[0]*(($position%2)===0?.25:.75));
                $y=(int)round($dims[1]*(intdiv($position,2)===0?.25:.75));
                $actual=imagecolorsforindex($output,imagecolorat($output,$x,$y));
                $distance=abs($actual['red']-$palette[$index][0])+abs($actual['green']-$palette[$index][1])+abs($actual['blue']-$palette[$index][2]);
                $assert($distance<80,'Contenu visuel EXIF '.$orientation.', coin '.$position);
            }
            unset($output);$removeResult($result);
        }

        // Mesure supplémentaire avec une vraie rotation de 3,84 MP, pas seulement les petites fixtures.
        $exif6="Exif\0\0II".pack('vVv',42,8,1).pack('vvV',0x0112,3,1).pack('v',6)."\0\0".pack('V',0);
        $largeJpeg=file_get_contents($large);$rotated=$root.'/rotated-four-megapixels.jpg';
        file_put_contents($rotated,substr($largeJpeg,0,2)."\xff\xe1".pack('n',strlen($exif6)+2).$exif6.substr($largeJpeg,2));
        $sources[$rotated]=hash_file('sha256',$rotated);
        foreach([$landscape,$portrait,$square,$large,$five,$rotated]as$file) {
            $process=proc_open([PHP_BINARY,'-d','memory_limit=128M',__FILE__,'measure',$file,$root],
                [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if(!is_resource($process))throw new RuntimeException('Worker mesures indisponible');
            fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            $assert(proc_close($process)===0&&$errors==='','Mesure isolée sans erreur : '.$errors);
            $measure=json_decode(trim($output),true,512,JSON_THROW_ON_ERROR);
            $assert($measure['phpPeakBytes']<128*1024*1024,'Pic PHP inférieur à 128M');
            if($file===$rotated)$assert($measure['orientation']===6&&$measure['orientedDimensions']===[1600,2400],'Mesure avec rotation JPEG réelle');
            echo 'IMAGE_MEASURE=',json_encode($measure),PHP_EOL;
        }
        // Frontière complète dans des processus frais : 24 MP admis sous 512M,
        // le même JPEG refusé AVANT GD sous 128M, sans modifier le php.ini.
        $phone=$fixture('boundary-24mp.jpg',4000,6000);gc_collect_cycles();gc_mem_caches();
        foreach([['512M','accept'],['128M','reject']]as[$memory,$mode]) {
            $worker=proc_open([PHP_BINARY,'-d','memory_limit='.$memory,__FILE__,'boundary',$phone,$root,$mode],
                [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if(!is_resource($worker))throw new RuntimeException('Worker frontière indisponible');
            fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            $assert(proc_close($worker)===0&&$errors==='','Frontière 24 MP : '.$errors);echo $output;
        }
        foreach($sources as$file=>$hash)$assert(hash_file('sha256',$file)===$hash,'Source inchangée');
        $assert(ini_get('memory_limit')===$originalMemoryLimit,'memory_limit inchangé');
        $assert(ini_get('gd.jpeg_ignore_warning')===$originalJpegWarnings,'Option libjpeg restaurée même après erreur');
        $assert(glob($root.'/tye-image-*')===[],'Aucun résultat temporaire oublié');
        echo "PRODUCT_IMAGE_PROCESSING_OK (MIME, EXIF 1-8, alpha, ratio, nettoyage, frontières 24 MP/6000, budgets PHP/applicatif, mémoire déjà utilisée).\n";
    } finally {
        $clean($root);
    }
}
