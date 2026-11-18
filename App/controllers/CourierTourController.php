<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Controller,Request,Response,Session};
use App\Middleware\CsrfMiddleware;
use App\Services\{CourierTourService,AdminAccessService};
use DomainException;
use Throwable;

/** HTML/POST/redirect : les identités ne sont jamais fournies par les formulaires Livreur. */
final class CourierTourController extends Controller
{
    public function __construct(private readonly ?CourierTourService $tours,private readonly AdminAccessService $admin,private readonly Request $request) {}
    public function index(): void {$this->show(false);}
    public function remittances(): void {$this->show(true);}
    public function start(): void {$this->post('start');}
    public function outcome(): void {$this->post('outcome');}
    public function returned(): void {$this->post('returned');}
    public function remit(): void {$this->post('remit');}
    public function link(): void {$this->post('link');}

    private function show(bool $admin): void
    {
        header('Cache-Control: no-store');
        try {
            if($this->request->method()!=='GET')throw new DomainException('Méthode non autorisée.',405);
            if(!$this->tours)throw new DomainException('Service indisponible.',503);
            $data=$admin?['remittances'=>$this->tours->remittances($this->admin)]:$this->tours->page();
            $data['activeParcel']=null;$data['activeTour']=null;
            // L'URL ne choisit qu'une vue parmi les colis déjà filtrés par l'identité serveur.
            if(!$admin&&array_key_exists('livraison',$this->request->queryParameters())){
                $attempt=$this->request->queryInteger('livraison');
                foreach($data['parcels'] as $parcel)if((int)$parcel['id_tentative']===$attempt)$data['activeParcel']=$parcel;
                if($attempt<1||$data['activeParcel']===null)throw new DomainException('Livraison introuvable.',404);
                foreach($data['tours'] as $tour)if((int)$tour['id_tournee']===(int)$data['activeParcel']['id_tournee'])$data['activeTour']=$tour;
                if($data['activeParcel']['resultat']!=='en_attente')Response::redirect('/livreur/livraisons');
            }
            $flash=Session::get('_tour_flash');Session::remove('_tour_flash');
            $this->render($admin?'admin/remittances':'courier/tours',$data+['flash'=>$flash,'title'=>$admin?'Argent des livraisons':'Mes livraisons','adminPage'=>'couriers','csrfToken'=>CsrfMiddleware::token(),'pageStyles'=>['/assets/css/courier-tours.css'],'pageScripts'=>['/assets/js/courier-tours.js']],$admin?'admin':'courier');
        }catch(Throwable $error){$this->error($error);}
    }

    private function post(string $action): void
    {
        header('Cache-Control: no-store');
        try {
            if(!$this->request->isPost())throw new DomainException('Méthode non autorisée.',405);
            if(!$this->tours)throw new DomainException('Service indisponible.',503);
            if(in_array($action,['remit','link'],true))$this->admin->requireCurrentAdmin();else $this->tours->identity();
            $input=$this->request->postParameters();
            if(!CsrfMiddleware::isValid($input['_token']??null))throw new DomainException('Session expirée. Rechargez la page.',419);
            $keys=match($action){'start'=>['tournee','version'],'outcome'=>['tentative','resultat','motif','commentaire'],'returned'=>['tournee'],'remit'=>['tournee','montants','mode','motif'],'link'=>['livreur','compte']};
            if(array_diff(array_keys($input),['_token',...$keys]))throw new DomainException('Paramètres non autorisés.',422);
            $number=static function(string $key,int $min=1)use($input):int{$n=filter_var($input[$key]??null,FILTER_VALIDATE_INT);if($n===false||$n===null||$n<$min)throw new DomainException('Référence invalide.',422);return $n;};
            $text=static fn(string $key):string=>is_string($input[$key]??null)?$input[$key]:'';
            if($action==='remit'&&!in_array($text('mode'),['','exact','ecart'],true))throw new DomainException('Action invalide.',422);
            if($action==='remit'&&$text('mode')==='exact'&&array_key_exists('montants',$input))throw new DomainException('Le montant attendu est calculé par le serveur.',422);
            $result=match($action){
                'start'=>$this->tours->start($number('tournee'),$number('version',0)),
                'outcome'=>$this->tours->outcome($number('tentative'),$text('resultat'),$text('motif'),$text('commentaire')),
                'returned'=>$this->tours->returned($number('tournee')),
                'remit'=>$this->tours->remit($number('tournee'),is_array($input['montants']??null)?$input['montants']:[],$this->admin,$text('mode')==='exact',$text('mode')==='ecart'?$text('motif'):null),
                'link'=>$this->tours->link($number('livreur'),$number('compte'),$this->admin),
            };
            $message=match($action){'start'=>'Colis récupérés. Bonne tournée !','outcome'=>$text('resultat')==='livree'?'Livraison confirmée et paiement reçu.':'Livraison non effectuée. Rapportez le colis au point de récupération.','returned'=>'Retour confirmé au point de récupération.',default=>'Enregistrement effectué.'};
            if($action==='remit'){
                $message=$text('mode')==='ecart'?'Écart signalé. Aucune remise validée ; les paiements restent à régulariser.':(($result['reused']?'Remise déjà confirmée : ':'Remise confirmée : ').implode(' + ',array_map(static fn(string $currency,string $amount):string=>number_format((float)$amount,2,',',' ').' '.$currency,array_keys($result['amounts']),array_values($result['amounts']))).' reçus de '.$result['courier'].'.');
            }
            Session::set('_tour_flash',$message);
            Response::redirect(match($action){'remit'=>'/admin/remises-cash','link'=>'/admin/livreurs',default=>'/livreur/livraisons'});
        }catch(Throwable $error){$this->error($error);}
    }

    private function error(Throwable $error): void
    {
        $status=$error instanceof DomainException&&in_array($error->getCode(),[401,403,404,405,409,419,422,503],true)?$error->getCode():503;
        if(!($error instanceof DomainException))error_log('Tournée : '.get_class($error).' ['.$error->getCode().']');
        http_response_code($status);
        $this->render('courier/error',['title'=>'Mes livraisons','csrfToken'=>CsrfMiddleware::token(),'error'=>$error instanceof DomainException?$error->getMessage():'Action impossible. Rechargez et réessayez.'],'courier');
    }
}
