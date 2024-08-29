<?php

namespace App\Http\Controllers\Artist;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Common;
use App\Models\Content;
use App\Models\Content_Episode;
use App\Models\Content_Play;
use App\Models\History;
use App\Models\Language;
use App\Models\Reviews;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class NovelController extends Controller
{
    private $folder = "content";
    public $common;
    public function __construct()
    {
        $this->common = new Common;
    }

    public function index(Request $request)
    {
        try {
            $artist = Artist_Data();
            $params['data'] = [];
            $params['category'] = Category::orderby('name', 'asc')->latest()->get();
            $params['language'] = Language::orderby('name', 'asc')->latest()->get();

            if ($request->ajax()) {

                $input_search = $request['input_search'];

                if ($input_search != null && isset($input_search)) {
                    $data = Content::where('title', 'LIKE', "%{$input_search}%")->where('artist_id', $artist['id'])->where('content_type', 2)->latest()->get();
                } else {
                    $data = Content::latest()->where('content_type', 2)->where('artist_id', $artist['id'])->get();
                }

                $this->common->imageNameToUrl($data, 'portrait_img', $this->folder);
                $this->common->imageNameToUrl($data, 'landscape_img', $this->folder);
                $this->common->videoNameToUrl($data, 'full_novel', $this->folder);

                return DataTables()::of($data)
                    ->addIndexColumn()
                    ->addColumn('action', function ($row) {
                        $delete = ' <form onsubmit="return confirm(\'Are you sure !!! You want to Delete this Novel ?\');" method="POST"  action="' . route('anovel.destroy', [$row->id]) . '">
                                <input type="hidden" name="_token" value="' . csrf_token() . '">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="edit-delete-btn" style="outline: none;" title="Delete"><i class="fa-solid fa-trash-can fa-xl"></i></button></form>';

                        $btn = '<div class="d-flex justify-content-around" title="Edit">';
                        $btn .= '<a class="edit-delete-btn edit_novel" title="Edit" data-toggle="modal" href="#EditModel" data-id="' . $row->id . '" data-title="' . $row->title . '" data-portrait_img="' . $row->portrait_img . '" data-landscape_img="' . $row->landscape_img . '" data-description="' . $row->description . '" data-category_id="' . $row->category_id . '" data-language_id="' . $row->language_id . '" data-full_novel="' . $row->full_novel . '" data-is_paid_novel="' . $row->is_paid_novel . '" data-novel_coin="' . $row->novel_coin . '">';
                        $btn .= '<i class="fa-solid fa-pen-to-square fa-xl"></i>';
                        $btn .= '</a>';
                        $btn .= $delete;
                        $btn .= '</a></div>';
                        return $btn;
                    })
                    ->addColumn('status', function ($row) {
                        if ($row->status == 1) {
                            return "<button type='button' id='$row->id' onclick='change_status($row->id, $row->status)' style='background:#4e45b8; font-weight:bold; border: none; color: white; padding: 5px 15px; outline: none;border-radius: 5px;cursor: pointer;'>Show</button>";
                        } else {
                            return "<button type='button' id='$row->id' onclick='change_status($row->id, $row->status)' style='background:#4e45b8; font-weight:bold; border: none; color: white; padding: 5px 20px; outline: none;border-radius: 5px;cursor: pointer;'>Hide</button>";
                        }
                    })
                    ->addColumn('episode', function ($row) {
                        $btn = '<a href="' . route('anovel.episode.index', $row->id) . '" class="btn text-white p-1 font-weight-bold" style="background:#4e45b8;">Chapter List</a> ';
                        return $btn;
                    })
                    ->rawColumns(['action', 'episode', 'status'])
                    ->make(true);
            }
            return view('artist.novel.index', $params);
        } catch (Exception $e) {
            return response()->json(array('status' => 400, 'errors' => $e->getMessage()));
        }
    }
    public function store(Request $request)
    {
        try {
            $artist = Artist_Data();

            $validator = Validator::make($request->all(), [
                'title' => 'required|min:2',
                'category_id' => 'required',
                'language_id' => 'required',
                'description' => 'required',
                'portrait_img' => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'landscape_img' => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'is_paid_novel' => 'required',
            ]);
            if ($validator->fails()) {
                $errs = $validator->errors()->all();
                return response()->json(array('status' => 400, 'errors' => $errs));
            }

            if ($request->is_paid_novel == 1) {
                $validator3 = Validator::make($request->all(), [
                    'novel_coin' => 'required|numeric|min:0',
                ]);
                if ($validator3->fails()) {
                    $errs3 = $validator3->errors()->all();
                    return response()->json(array('status' => 400, 'errors' => $errs3));
                }
            }

            $requestData = $request->all();
            $requestData['artist_id'] = $artist['id'];

            $files = $requestData['portrait_img'];
            $files1 = $requestData['landscape_img'];
            $requestData['portrait_img'] = $this->common->saveImage($files, $this->folder);
            $requestData['landscape_img'] = $this->common->saveImage($files1, $this->folder);
            $requestData['content_type'] = 2;
            if ($requestData['full_novel'] != null && isset($requestData['full_novel'])) {
                $requestData['full_novel'] = $requestData['full_novel'];
            } else {
                $requestData['full_novel'] = "";
            }
            if ($requestData['is_paid_novel'] == 0) {
                $requestData['novel_coin'] = 0;
            }
            $requestData['total_played'] = 0;
            $requestData['status'] = 1;

            $content_data = Content::updateOrCreate(['id' => $requestData['id']], $requestData);
            if (isset($content_data->id)) {

                // Send Notification
                $imageURL = $this->common->getImage($this->folder, $content_data->portrait_img);
                $noti_array = array(
                    'id' => $content_data->id,
                    'title' => $content_data->title,
                    'image' => $imageURL,
                    'content_type' => $content_data->content_type,
                    'description' => string_cut($content_data->description, 90),
                );
                $this->common->sendNotification($noti_array);

                return response()->json(array('status' => 200, 'success' => __('Label.data_add_successfully')));
            } else {
                return response()->json(array('status' => 400, 'errors' => __('Label.data_not_added')));
            }
        } catch (Exception $e) {
            return response()->json(array('status' => 400, 'errors' => $e->getMessage()));
        }
    }
    public function update($id, Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|min:2',
                'category_id' => 'required',
                'language_id' => 'required',
                'description' => 'required',
                'portrait_img' => 'image|mimes:jpeg,png,jpg|max:2048',
                'landscape_img' => 'image|mimes:jpeg,png,jpg|max:2048',
                'edit_is_paid_novel' => 'required',
            ]);
            if ($validator->fails()) {
                $errs = $validator->errors()->all();
                return response()->json(array('status' => 400, 'errors' => $errs));
            }

            if ($request->edit_is_paid_novel == 1) {
                $validator3 = Validator::make($request->all(), [
                    'novel_coin' => 'required|numeric|min:0',
                ]);
                if ($validator3->fails()) {
                    $errs3 = $validator3->errors()->all();
                    return response()->json(array('status' => 400, 'errors' => $errs3));
                }
            }

            $requestData = $request->all();

            $requestData['is_paid_novel'] = $requestData['edit_is_paid_novel'];
            if (isset($requestData['portrait_img'])) {
                $files = $requestData['portrait_img'];
                $requestData['portrait_img'] = $this->common->saveImage($files, $this->folder);
                $this->common->deleteImageToFolder($this->folder, basename($requestData['old_portrait_img']));
            }
            if (isset($requestData['landscape_img'])) {
                $files = $requestData['landscape_img'];
                $requestData['landscape_img'] = $this->common->saveImage($files, $this->folder);
                $this->common->deleteImageToFolder($this->folder, basename($requestData['old_landscape_img']));
            }
            if ($requestData['full_novel'] != null && isset($requestData['full_novel'])) {
                $requestData['full_novel'] = $requestData['full_novel'];
                $this->common->deleteImageToFolder($this->folder, basename($requestData['old_full_novel']));
            } else {
                $requestData['full_novel'] = basename($requestData['old_full_novel']);
            }
            if ($requestData['edit_is_paid_novel'] == 0) {
                $requestData['novel_coin'] = 0;
            }
            unset($requestData['old_portrait_img'], $requestData['old_landscape_img'], $requestData['old_full_novel'], $requestData['edit_is_paid_novel']);

            $content_data = Content::updateOrCreate(['id' => $requestData['id']], $requestData);
            if (isset($content_data->id)) {
                return response()->json(array('status' => 200, 'success' => __('Label.data_edit_successfully')));
            } else {
                return response()->json(array('status' => 400, 'errors' => __('Label.data_not_updated')));
            }
        } catch (Exception $e) {
            return response()->json(array('status' => 400, 'errors' => $e->getMessage()));
        }
    }
    public function destroy($id)
    {
        try {
            $data = Content::where('id', $id)->first();

            if (isset($data)) {

                $this->common->deleteImageToFolder($this->folder, $data['portrait_img']);
                $this->common->deleteImageToFolder($this->folder, $data['landscape_img']);
                $this->common->deleteImageToFolder($this->folder, $data['full_novel']);
                $data->delete();

                $episode = Content_Episode::where('content_id', $id)->get();
                for ($i = 0; $i < count($episode); $i++) {
                    $this->common->deleteImageToFolder($this->folder, $episode[$i]['image']);
                    $this->common->deleteImageToFolder($this->folder, $episode[$i]['book']);
                    $episode[$i]->delete();
                }

                Reviews::where('content_id', $id)->delete();
                Content_Play::where('content_type', 2)->where('content_id', $id)->delete();
                History::where('content_type', 2)->where('content_id', $id)->delete();
                Banner::where('content_type', 1)->where('content_id', $id)->delete();
            }

            return redirect()->route('anovel.index')->with('success', __('Label.data_delete_successfully'));
        } catch (Exception $e) {
            return response()->json(array('status' => 400, 'errors' => $e->getMessage()));
        }
    }
    public function show($id)
    {
        try {
            $data = Content::where('id', $id)->first();
            if ($data->status == 0) {
                $data->status = 1;
            } elseif ($data->status == 1) {
                $data->status = 0;
            } else {
                $data->status = 0;
            }
            $data->save();
            return response()->json(array('status' => 200, 'success' => 'Status Changed', 'id' => $data->id, 'Status_Code' => $data->status));
        } catch (Exception $e) {
            return response()->json(array('status' => 400, 'errors' => $e->getMessage()));
        }
    }
    // Episode
    public function NovelIndex($id, Request $request)
    {
        try {
            Artist_Data();

            $params['data'] = [];
            $params['novel_id'] = $id;
            $input_search = $request['input_search'];

            if ($input_search != null && isset($input_search)) {
                $params['data'] = Content_Episode::where('name', 'LIKE', "%{$input_search}%")->where('content_id', $id)->orderBy('sortable', 'asc')->latest()->paginate(15);
            } else {
                $params['data'] = Content_Episode::where('content_id', $id)->orderBy('sortable', 'asc')->latest()->paginate(15);
            }

            $this->common->imageNameToUrl($params['data'], 'image', $this->folder);

            return view('artist.novel.ep_index', $params);
        } catch (Exception $e) {
            return response()->json(array('status' => 400, 'errors' => $e->getMessage()));
        }
    }
    public function NovelAdd($id)
    {
        try {
            $params['novel_id'] = $id;
            return view('artist.novel.ep_add', $params);
        } catch (Exception $e) {
            return response()->json(array('status' => 400, 'errors' => $e->getMessage()));
        }
    }
    public function NovelSave(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'content_id' => 'required',
                'name' => 'required',
                'description' => 'required',
                'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'is_book_paid' => 'required',
                'book' => 'required',
            ]);
            if ($validator->fails()) {
                $errs = $validator->errors()->all();
                return response()->json(array('status' => 400, 'errors' => $errs));
            }

            if ($request->is_book_paid == 1) {
                $validator3 = Validator::make($request->all(), [
                    'is_book_coin' => 'required|numeric|min:0',
                ]);
                if ($validator3->fails()) {
                    $errs3 = $validator3->errors()->all();
                    return response()->json(array('status' => 400, 'errors' => $errs3));
                }
            }

            $requestData = $request->all();

            $files = $requestData['image'];
            $requestData['image'] = $this->common->saveImage($files, $this->folder);
            $requestData['audio_type'] = 0;
            $requestData['audio'] = "";
            $requestData['audio_duration'] = 0;
            $requestData['is_audio_paid'] = 0;
            $requestData['is_audio_coin'] = 0;
            $requestData['total_audio_played'] = 0;
            $requestData['video_type'] = 0;
            $requestData['video'] = "";
            $requestData['video_duration'] = 0;
            $requestData['is_video_paid'] = 0;
            $requestData['is_video_coin'] = 0;
            $requestData['total_video_played'] = 0;
            if ($requestData['is_book_paid'] == 0) {
                $requestData['is_book_coin'] = 0;
            }
            $requestData['total_book_played'] = 0;
            $requestData['sortable'] = 1;
            $requestData['status'] = 1;

            $episode_data = Content_Episode::updateOrCreate(['id' => $requestData['id']], $requestData);
            if (isset($episode_data->id)) {
                return response()->json(array('status' => 200, 'success' => __('Label.data_add_successfully')));
            } else {
                return response()->json(array('status' => 400, 'errors' => __('Label.data_not_added')));
            }
        } catch (Exception $e) {
            return response()->json(array('status' => 400, 'errors' => $e->getMessage()));
        }
    }
    public function NovelEdit($novel_id, $id)
    {
        try {

            $params['data'] = Content_Episode::where('id', $id)->first();
            if ($params['data'] != null) {

                $params['novel_id'] = $novel_id;
                $this->common->imageNameToUrl(array($params['data']), 'image', $this->folder);
                $this->common->videoNameToUrl(array($params['data']), 'book', $this->folder);

                return view('artist.novel.ep_edit', $params);
            } else {
                return redirect()->back()->with('error', __('Label.page_not_found'));
            }
        } catch (Exception $e) {
            return response()->json(array('status' => 400, 'errors' => $e->getMessage()));
        }
    }
    public function NovelUpdate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'content_id' => 'required',
                'name' => 'required',
                'description' => 'required',
                'image' => 'image|mimes:jpeg,png,jpg|max:2048',
                'is_book_paid' => 'required',
            ]);
            if ($validator->fails()) {
                $errs = $validator->errors()->all();
                return response()->json(array('status' => 400, 'errors' => $errs));
            }
            if ($request->is_book_paid == 1) {
                $validator3 = Validator::make($request->all(), [
                    'is_book_coin' => 'required|numeric|min:0',
                ]);
                if ($validator3->fails()) {
                    $errs3 = $validator3->errors()->all();
                    return response()->json(array('status' => 400, 'errors' => $errs3));
                }
            }

            $requestData = $request->all();

            if (isset($requestData['image'])) {
                $files = $requestData['image'];
                $requestData['image'] = $this->common->saveImage($files, $this->folder);
                $this->common->deleteImageToFolder($this->folder, basename($requestData['old_image']));
            }
            if ($requestData['book'] && $requestData['book'] != null && isset($requestData['book'])) {

                $requestData['book'] = $requestData['book'];
                $this->common->deleteImageToFolder($this->folder, basename($requestData['old_book']));
            } else {
                $requestData['book'] = basename($requestData['old_book']);
            }
            if ($requestData['is_book_paid'] == 0) {
                $requestData['is_book_coin'] = 0;
            }
            unset($requestData['old_image'], $requestData['old_book']);

            $episode_data = Content_Episode::updateOrCreate(['id' => $requestData['id']], $requestData);
            if (isset($episode_data->id)) {
                return response()->json(array('status' => 200, 'success' => __('Label.data_edit_successfully')));
            } else {
                return response()->json(array('status' => 400, 'errors' => __('Label.data_not_updated')));
            }
        } catch (Exception $e) {
            return response()->json(array('status' => 400, 'errors' => $e->getMessage()));
        }
    }
    public function NovelDelete($podcasts_id, $id)
    {
        try {

            $data = Content_Episode::where('id', $id)->first();
            if (isset($data)) {

                $this->common->deleteImageToFolder($this->folder, $data['image']);
                $this->common->deleteImageToFolder($this->folder, $data['book']);
                $data->delete();

                Content_Play::where('content_type', 2)->where('content_id', $podcasts_id)->where('content_episode_id', $id)->delete();
                History::where('content_type', 2)->where('content_id', $podcasts_id)->where('content_episode_id', $id)->delete();
            }

            return redirect()->route('anovel.episode.index', $podcasts_id)->with('success', __('Label.data_delete_successfully'));
        } catch (Exception $e) {
            return response()->json(array('status' => 400, 'errors' => $e->getMessage()));
        }
    }
    public function NovelSortable(Request $request)
    {
        try {

            $ids = $request['ids'];

            if (isset($ids) && $ids != null && $ids != "") {

                $id_array = explode(',', $ids);
                for ($i = 0; $i < count($id_array); $i++) {
                    Content_Episode::where('id', $id_array[$i])->update(['sortable' => $i + 1]);
                }
            }
            return response()->json(array('status' => 200, 'success' => __('Label.data_edit_successfully')));
        } catch (Exception $e) {
            return response()->json(array('status' => 400, 'errors' => $e->getMessage()));
        }
    }
}
