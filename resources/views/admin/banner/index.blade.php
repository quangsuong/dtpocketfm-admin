@extends('admin.layout.page-app')
@section('page_title', 'Home Banner')

@section('content')
    @include('admin.layout.sidebar')

    <div class="right-content">
        @include('admin.layout.header')

        <div class="body-content">
            <!-- mobile title -->
            <h1 class="page-title-sm">Home Banner</h1>

            <div class="border-bottom row">
                <div class="col-sm-12">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{__('Label.Dashboard')}}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Home Banner</li>
                    </ol>
                </div>
            </div>

            <ul class="tabs nav nav-pills custom-tabs inline-tabs " id="pills-tab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="app-tab" onclick="Top_Content('1', '0')" data-is_home_screen="1"
                        data-id="0" data-toggle="pill" href="#app" role="tab" aria-controls="app" aria-selected="true">Home</a>
                </li>
                @for ($i = 0; $i < count($category); $i++) 
                <li class="nav-item">
                    <a class="nav-link" id="{{$category[$i]['name']}}-tab" onclick="Top_Content('2' , '{{$category[$i]['id']}}')" data-is_home_screen="2" data-id="{{$category[$i]['id']}}" 
                        data-toggle="pill" href="#{{$category[$i]['name']}}" role="tab" aria-controls="{{$category[$i]['name']}}" aria-selected="true">{{ $category[$i]['name']}}</a>
                </li>
                @endfor
            </ul>

            <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show active" id="app" role="tabpanel" aria-labelledby="app-tab">
                    <div class="card custom-border-card">
                        <h5 class="card-header">Add Banner</h5>
                        <div class="card-body">
                            <form id="save_banner">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <div class="form-row">
                                    <div class="col-md-2 mr-3 content_type">
                                        <div class="form-group mt-4">
                                            <label>Content Type</label>
                                            <select class="form-control" name="content_type" id="content_type">
                                                <option value="" selected>Select Content Type</option>
                                                <option value="1">Audio Book</option>
                                                <option value="2">Novel</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mt-4">
                                            <label>Content</label>
                                            <select class="form-control" name="content_id" id="content_id" style="width:100%!important;">
                                                <option value="" selected disabled>Select Content</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <div class="after-add-more"></div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('pagescript')
    <script>
        $("#content_id").select2();

        var Tab = $("ul.tabs li a.active");
        var Is_home_screen = Tab.data("is_home_screen");
        var TopCategoryId = 0;
        $('.nav-item a').on('click', function() {
            Is_home_screen = $(this).data("is_home_screen");
            TopCategoryId = $(this).data("id");
        });

        // Content_Type 
        $("#content_type").change(function() {

            $("#content_id").empty();
            $('#content_id').append(`<option value="" selected disabled>Select Content</option>`);

            var content_type = $(this).children("option:selected").val();
            if(content_type == 1 || content_type == 2){
                
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    type: 'POST',
                    url: '{{ route("typeByContent") }}',
                    data: {
                        content_type:content_type,
                        is_home_screen:Is_home_screen,
                        top_category_id:TopCategoryId,
                    },
                    success: function(resp) {

                        for (var i = 0; i < resp.result.length; i++) {
                            $('#content_id').append(`<option value="${resp.result[i].id}">${resp.result[i].title}</option>`);          
                        }
                    },
                    error: function(XMLHttpRequest, textStatus, errorThrown) {
                        toastr.error(errorThrown.msg, 'failed');
                    }
                });
            }
        });

        // Save
        $('#content_id').on('change', function () {

            var Content_Type = $('#content_type').find(":selected").val();
            var Content_Id = $(this).children("option:selected").val();

            $("#dvloader").show();
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: 'POST',
                url: '{{ route("banner.store") }}',
                data: {
                    is_home_screen:Is_home_screen,
                    top_category_id:TopCategoryId,
                    content_type:Content_Type,
                    content_id: Content_Id,
                },
                success: function(resp) {
                    $("#dvloader").hide();
                    get_responce_message(resp, 'save_banner', '{{ route("banner.index") }}');
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    $("#dvloader").hide();
                    toastr.error(errorThrown.msg, 'failed');
                }
            });
        });

        // List Section
        if(Is_home_screen == 1) {

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: "POST",
                data: {is_home_screen:Is_home_screen},
                url: '{{ route("bannerList") }}',
                success: function(resp) {
                    
                    if(resp.result.length > 0){
                        var data ='<div class="form-group row mb-0 pb-0">' +
                                    '<div class="col-md-2">' +
                                        '<label>Content Type</label>' +
                                    '</div>' +
                                    '<div class="col-md-4">' +
                                        '<label>Content</label>' +
                                    '</div>' +
                                '</div>';
                        $('.after-add-more').append(data);
                    }

                    for (var i = 0; i < resp.result.length; i++) {
                        
                        if (resp.result[i].content.content_type == 1) {
                            var content_type = "Audio Book";
                        } else if (resp.result[i].content.content_type == 2) {
                            var content_type = "Novel";
                        } else {
                            var content_type = "-";
                        }

                        var data ='<div class="form-group row">' +
                                    '<div class="col-md-2">' +
                                        '<input type="text" class="form-control" value="'+ content_type +'" readonly/>' +
                                    '</div>' +
                                    '<div class="col-md-4">' +
                                        '<input type="text" class="form-control" value="'+ resp.result[i].content.title +'" readonly/>' +
                                    '</div>' +
                                    '<div class="col-md-1">' +
                                        '<a onclick="DeleteBanner('+ resp.result[i].id +')" class="btn btn-danger" title="Delete"><i class="fa-solid fa-trash-can fa-xl"></i></a>' +                                   
                                    '</div>' +
                                '</div>';
                        $('.after-add-more').append(data);
                    }
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    toastr.error(errorThrown.msg, 'failed');
                }
            });
        }
        function Top_Content(is_home_screen, top_category_id) {

            $("#content_type").children().removeAttr("selected");
            $("#content_id").empty();
            $('#content_id').append(`<option value="" selected disabled>Select Content</option>`);

            $(".after-add-more .row").remove();

            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: "POST",
                data: {
                    is_home_screen:is_home_screen,
                    top_category_id:top_category_id
                },
                url: '{{ route("bannerList") }}',
                success: function(resp) {
                    
                    if(resp.result.length > 0){
                        var data ='<div class="form-group row mb-0 pb-0">' +
                                    '<div class="col-md-2">' +
                                        '<label>Content Type</label>' +
                                    '</div>' +
                                    '<div class="col-md-4">' +
                                        '<label>Content</label>' +
                                    '</div>' +
                                '</div>';
                        $('.after-add-more').append(data);
                    }

                    for (var i = 0; i < resp.result.length; i++) {
                        
                        if (resp.result[i].content.content_type == 1) {
                            var content_type = "Audio Book";
                        } else if (resp.result[i].content.content_type == 2) {
                            var content_type = "Novel";
                        } else {
                            var content_type = "-";
                        }

                        var data ='<div class="form-group row">' +
                                    '<div class="col-md-2">' +
                                        '<input type="text" class="form-control" value="'+ content_type +'" readonly/>' +
                                    '</div>' +
                                    '<div class="col-md-4">' +
                                        '<input type="text" class="form-control" value="'+ resp.result[i].content.title +'" readonly/>' +
                                    '</div>' +
                                    '<div class="col-md-1">' +
                                        '<a onclick="DeleteBanner('+ resp.result[i].id +')" class="btn btn-danger" title="Delete"><i class="fa-solid fa-trash-can fa-xl"></i></a>' +                                   
                                    '</div>' +
                                '</div>';
                        $('.after-add-more').append(data);
                    }
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    toastr.error(errorThrown.msg, 'failed');
                }
            });
        };

        // Delete Banner
        function DeleteBanner(id) {

            var url = "{{route('banner.destroy', '')}}"+"/"+id;

            $("#dvloader").show();
            $.ajax({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                type: 'DELETE',
                url:url,
                success: function(resp) {
                    $("#dvloader").hide();
                    get_responce_message(resp, 'save_banner', '{{ route("banner.index") }}');
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    $("#dvloader").hide();
                    toastr.error(errorThrown.msg, 'failed');
                }
            });
        }
    </script>
@endsection