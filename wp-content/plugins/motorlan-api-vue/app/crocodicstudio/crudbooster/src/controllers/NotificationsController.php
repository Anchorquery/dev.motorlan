<?php namespace crocodicstudio\crudbooster\controllers;

use CRUDBooster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Excel;
use Illuminate\Support\Facades\PDF;
use Illuminate\Support\Facades\View;

class NotificationsController extends CBController
{
    public function cbInit()
    {
        $this->table = "cms_notifications";
        $this->primary_key = "id";
        $this->title_field = "content";
        $this->limit = 20;
        $this->index_orderby = ["id" => "desc"];
        $this->button_show = FALSE;	
        $this->button_add = false;
        $this->button_edit = true;
        $this->button_delete = true;
        $this->button_export = false;
        $this->button_import = false;
        $this->global_privilege = false;

        $read_notification_url = url(config('crudbooster.ADMIN_PATH')).'/notifications/read';

        $this->col = [];
        $this->col[] = ["label" => "Contenido", "name" => "content", "callback_php" => '"<a href=\"'.$read_notification_url.'/$row->id\">$row->content</a>"'];
        
        $this->col[] = [
            'label' => 'Leido',
            'name' => 'is_read',
            'callback_php' => '($row->is_read)?"<span class=\"label label-default\">Ya leido</span>":"<span class=\"label label-danger\">Nuevo</span>"',
        ];

        $this->form = [];
        $this->form[] = ["label" => "Content", "name" => "content", "type" => "text"];
        $this->form[] = ["label" => "Icon", "name" => "icon", "type" => "text"];
        $this->form[] = ["label" => "Notification Command", "name" => "notification_command", "type" => "textarea"];
        $this->form[] = ["label" => "Is Read", "name" => "is_read", "type" => "text"];

        $this->style_css = ".btn-detail{display:none!important;}"; 
        
    }

    public function hook_query_index(&$query)
    {
        $query->where('id_cms_users', CRUDBooster::myId());
    }

    public function getLatestJson()
    {

        $rows = DB::table('cms_notifications')->where('id_cms_users', 0)->orWhere('id_cms_users', CRUDBooster::myId())->orderby('id', 'desc')->where('is_read', 0)->take(25);
        if (\Schema::hasColumn('cms_notifications', 'deleted_at')) {
            $rows->whereNull('deleted_at');
        }

        $total = count($rows->get());

        return response()->json(['items' => $rows->get(), 'total' => $total]);
    }

    public function getRead($id)
    {
       // $data = new \stdClass();
        
        DB::table('cms_notifications')->where('id', $id)->update(['is_read' => 1]);
        
        $notificacion = DB::table('cms_notifications')->where('id', $id)->first();
        $datos = DB::table('socio_modificaciones_front_log')->where('id', $notificacion->detalles)->first();

        $data['usuario_modificador'] = DB::table('personas')->where('PERSONA_ID_PK', $datos->USER_ID)->first();
        //dd($row);
        $data['socio'] = DB::table('socios')->where('SOCIO_ID_PK', $datos->SOCIO_ID_FK)->first();

        $data['cnae'] = DB::table('cnae')->get();
        $data['forma_juridica'] = DB::table('socio_forma_juridica')->get();
        $data['caracter'] = DB::table('socio_caracter')->get();
        $data['tipo_entidad'] = DB::table('dominios')->where('TIPO', 5)->get();
        $data['areas'] = DB::table('areas')->get();

        $data['valores_nuevos'] = json_decode($datos->valores_nuevos);
        $data['valores_viejos'] =json_decode($datos->valores_antiguos);
        $data['accion'] = $datos->ACCION;
        $data['detalles'] = $datos->DETALLES;
        $data['page_title'] = 'Notificación '.$datos->ACCION;
   
        return view('notificacion',$data);
    
    }
    public function getEdit($id){
		
        DB::table('cms_notifications')->where('id', $id)->update(['is_read' => 1]);
        
        $notificacion = DB::table('cms_notifications')->where('id', $id)->first();
        $datos = DB::table('socio_modificaciones_front_log')->where('id', $notificacion->detalles)->first();

        $data['usuario_modificador'] = DB::table('personas')->where('PERSONA_ID_PK', $datos->USER_ID)->first();
        //dd($row);
        $data['socio'] = DB::table('socios')->where('SOCIO_ID_PK', $datos->SOCIO_ID_FK)->first();

        $data['cnae'] = DB::table('cnae')->get();
        $data['forma_juridica'] = DB::table('socio_forma_juridica')->get();
        $data['caracter'] = DB::table('socio_caracter')->get();
        $data['tipo_entidad'] = DB::table('dominios')->where('TIPO', 5)->get();
        $data['areas'] = DB::table('areas')->get();

        $data['valores_nuevos'] = json_decode($datos->valores_nuevos);
        $data['valores_viejos'] =json_decode($datos->valores_antiguos);
        $data['accion'] = $datos->ACCION;
        $data['detalles'] = $datos->DETALLES;
        $data['page_title'] = 'Notificación '.$datos->ACCION;
   
        return view('notificacion',$data);
    }
}