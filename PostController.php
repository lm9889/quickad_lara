<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostOption;
use Illuminate\Http\Request;
use Validator;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $params = $columns = $order = $totalRecords = $data = array();
            $params = $request;

            //define index of column
            $columns = array(
                'id',
                'product_name',
                'created_at'
            );

            if(!empty($params['search']['value'])){
                $q = $params['search']['value'];
                $posts = Post::where('product_name', 'like', '%' . $q . '%')
                    ->OrWhere('description', 'like', '%' . $q . '%')
                    ->with(['user','category_data','sub_category_data'])
                    ->orderBy($columns[$params['order'][0]['column']],$params['order'][0]['dir'])
                    ->limit($params['length'])->offset($params['start'])
                    ->get();
            }else{
                $posts = Post::with(['user','category_data','sub_category_data'])
                    ->orderBy($columns[$params['order'][0]['column']],$params['order'][0]['dir'])
                    ->limit($params['length'])->offset($params['start'])
                    ->get();
            }

            $totalRecords = Post::count();
            foreach ($posts as $row) {
                $picture     =   explode(',' ,$row['screen_shot']);
                if($picture[0] != ""){
                    $image = $picture[0];
                }else{
                    $image = "default.png";
                }

                if ($row->status == Post::STATUS_PENDING) {
                    $status_badge = '<span class="badge bg-info">' . ___('Pending') . '</span>';
                } elseif ($row->status == Post::STATUS_ACTIVE) {
                    $status_badge = '<span class="badge bg-success">' . ___('Approved') . '</span>';
                } elseif ($row->status == Post::STATUS_EXPIRE) {
                    $status_badge = '<span class="badge bg-danger">' . ___('Expire') . '</span>';
                } elseif ($row->status == Post::STATUS_REJECTED) {
                    $status_badge = '<span class="badge bg-warning">' . ___('Rejected') . '</span>';
                }

                $rows = array();
                $rows[] = '<td>'.$row->id.'</td>';
                $rows[] = '<td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar me-2 min-w-38">
                                        <a href="'.route('publicView', $row->slug).'" target="_blank">
                                            <img alt="'.$row->product_name.'"
                                            src="'.asset('storage/products/thumb/'.$image).'" />
                                        </a>
                                    </div>
                                    <div>
                                        <a class="text-body fw-semibold text-one-line word-break"
                                            href="'.route('publicView', $row->slug).'">
                                            '.$row->product_name.'
                                        </a>
                                        <p class="text-muted mb-0">
                                            '.$row->category_data->cat_name.' -
                                            '.$row->sub_category_data->sub_cat_name.'
                                        </p>
                                    </div>
                                </div>
                            </td>';
                $rows[] = '<td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar me-2 min-w-38">
                                        <a href="'.route('admin.users.edit', $row->user->id).'">
                                            <img alt="'.$row->user->username.'" src="'.asset('storage/profile/'.$row->user->image).'" />
                                        </a>
                                    </div>
                                    <div>
                                        <a class="text-body fw-semibold text-one-line word-break"
                                            href="'.route('admin.users.edit', $row->user->id).'">'.$row->user->name.'</a>
                                        <p class="text-muted mb-0">@'.$row->user->username.'</p>
                                    </div>
                                </div>
                            </td>';
                $rows[] = '<td>'.$status_badge.'</td>';
                $rows[] = '<td>'.date_formating($row->created_at).'</td>';
                $rows[] = '<td>
                                <div class="d-flex">
                                    <a href="'.route('publicView', $row->slug).'" title="'.___('View').'" class="btn btn-primary btn-icon" data-tippy-placement="top" target="_blank"><i class="icon-feather-eye"></i></a>
                                </div>
                            </td>';
                $rows[] = '<td>
                                <div class="checkbox">
                                <input type="checkbox" id="check_'.$row->id.'" value="'.$row->id.'" class="quick-check">
                                <label for="check_'.$row->id.'"><span class="checkbox-icon"></span></label>
                            </div>
                           </td>';
                $rows['DT_RowId'] = $row->id;
                $data[] = $rows;
            }

            $json_data = array(
                "draw"            => intval( $params['draw'] ),
                "recordsTotal"    => intval( $totalRecords ),
                "recordsFiltered" => intval($totalRecords),
                "data"            => $data   // total data array
            );
            return response()->json($json_data, 200);
        }

        return view('admin.posts.index');
    }

     /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $categories = Category::all();
        return view('admin.posts.create', compact('categories'));
    }

    public function getSubCategories($categoryId)
    {
        $subCategories = Category::find($categoryId)->subcategories()->get();

        return response()->json($subCategories);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Post $post
     * @return \Illuminate\Http\Response
     */
    public function delete(Request $request)
    {
        $ids = array_map('intval', $request->ids);
        $posts = Post::whereIn('id',$ids)->get();
        foreach($posts as $post){
            $screen_sm = explode(',',$post['screen_shot']);
            foreach ($screen_sm as $value)
            {
                remove_file('storage/products/' . $value);
                remove_file('storage/products/thumb/' . $value);
            }
        }
        Post::whereIn('id',$ids)->delete();

        $result = array('success' => true, 'message' => ___('Deleted Successfully'));
        return response()->json($result, 200);
    }

}
