<?php
declare(strict_types=1);
namespace App\Services;

require_once __DIR__.'/ProductVariantWriter.php';

/** Bounded native form, shared by preview, no-JS submissions and final validation. */
final class ProductCreationInput
{
    public function __construct(private readonly ProductVariantWriter $writer,private readonly ProductVariantInput $input) {}

    public static function guard(array $input): void
    {
        if (!array_key_exists('product_type',$input)) return; // Existing callers retain their contract.
        $count=0;
        array_walk_recursive($input,static function()use(&$count):void {$count++;});
        $limit=(int)ini_get('max_input_vars');
        if (($limit>0&&$count>=$limit)||($input['creation_end']??null)!=='v1') {
            throw new ProductFormValidationException('creation','Formulaire incomplet ou tronqué. Rien n’a été enregistré. Rechargez le formulaire et vérifiez votre saisie.');
        }
        if (!in_array($input['product_type'],['simple','variable'],true)) throw new ProductFormValidationException('creation','Choisissez un produit simple ou avec variantes.');
    }

    public function preview(array $input): array
    {
        $raw=$input['creation_options']??[];
        if (!is_array($raw)||!array_is_list($raw)||count($raw)>3) throw new ProductFormValidationException('creation','Trois options maximum.');
        $definitions=[];
        foreach ($raw as $row) {
            if (!is_array($row)||array_diff(array_keys($row),['nom','valeurs'])||!is_string($row['nom']??null)||!is_string($row['valeurs']??null)) throw new ProductFormValidationException('creation','Options invalides.');
            if (strlen($row['valeurs'])>41000) throw new ProductFormValidationException('creation','Cent valeurs maximum par option.');
            if (trim($row['nom'])===''&&trim($row['valeurs'])==='') continue;
            $values=preg_split('/\R/u',trim($row['valeurs']));
            if ($values===false) throw new ProductFormValidationException('creation','Texte des options invalide.');
            $definitions[]=['nom'=>$row['nom'],'valeurs'=>$values];
        }
        try {$preview=$this->writer->candidates($definitions);}
        catch (\DomainException $e) {throw new ProductFormValidationException('creation',$e->getMessage());}
        $preview['signature']=hash('sha256',json_encode($preview['definitions'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        return $preview;
    }

    public function variants(array $input,array $preview): array
    {
        if (($input['creation_signature']??null)!==$preview['signature']) throw new ProductFormValidationException('creation','Les options ont changé. Affichez de nouveau les combinaisons et vérifiez votre sélection.');
        $rows=$input['creation_variants']??[];
        if (!is_array($rows)||count($rows)!==count($preview['candidates'])||array_diff(array_keys($rows),array_keys($preview['candidates']))) throw new ProductFormValidationException('creation','Combinaisons incomplètes. Affichez de nouveau les combinaisons.');
        $variants=[];$errors=[];$skus=[];
        foreach ($preview['candidates'] as $token=>$candidate) {
            $row=$rows[$token]??null;$key='creation_variants.'.$token;
            if (!is_array($row)||array_diff(array_keys($row),['selected','prix','devise','stock','statut','sku_mode','sku'])) throw new ProductFormValidationException('creation','Champs de variante invalides.');
            foreach (['prix','devise','stock','statut','sku_mode','sku'] as $field) {
                if (!is_string($row[$field]??null)) throw new ProductFormValidationException('creation','Formulaire de variantes incomplet. Rien n’a été enregistré.');
            }
            if (!array_key_exists('selected',$row)) continue;
            if ($row['selected']!=='1') throw new ProductFormValidationException('creation','Sélection invalide.');
            try {
                $price=$this->input->price($row['prix'],$row['devise']);
                $stock=$this->writer->integer($row['stock'],0,4294967295);
                if (!in_array($row['statut'],['actif','inactif'],true)) throw new \DomainException('Choisissez Actif ou Inactif.');
                if (!in_array($row['sku_mode'],['auto','manual'],true)) throw new \DomainException('Choisissez un mode SKU valide.');
                $sku=$row['sku_mode']==='auto'?'':$this->input->sku($row['sku']);
                if ($row['sku_mode']==='manual'&&$sku==='') throw new \DomainException('Saisissez la référence manuelle.');
                if ($sku!==''&&isset($skus[$sku])) throw new \DomainException('Cette référence apparaît sur plusieurs variantes.');
                if ($sku!=='') $skus[$sku]=true;
                $variants[]=['selection'=>$candidate['selection'],'prix'=>$price,'devise'=>'USD','stock'=>$stock,'statut'=>$row['statut'],'sku'=>$sku];
            } catch (\DomainException $e) {$errors[$key]=$candidate['label'].' : '.$e->getMessage();}
        }
        if ($errors!==[]) throw new ProductFormValidationException($errors);
        if ($variants===[]) throw new ProductFormValidationException('creation','Sélectionnez au moins une combinaison commercialisée.');
        return $variants;
    }
}
