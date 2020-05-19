<?php

namespace App\Http\Controllers;

use App\album;
use App\artist;
use App\roles_relations;
use App\track;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return Renderable
     */
    public function index()
    {

        $user = Auth::user();
        return view('home', compact('user'));
    }

    public function profile()
    {

        $user = Auth::user();

        $user_id = $user->id;

        $userChecker = DB::table('roles_relations')
            ->where('id_user', $user_id)
            ->count();

        $userRole = roles_relations::find($user_id);

        if ($userChecker < 1) {
            $roles_relations = new roles_relations;
            $roles_relations->id_user = $user_id;
            $roles_relations->id_roles = 5;
            $roles_relations->save();
        }

//        Returns the current role of the user
        $role = $userRole->id_roles;
        $user_role = DB::table('roles')
            ->where('id', $role)
            ->first();

        $roleValue = $user_role->name;


        return view('profile_information', compact('user', 'roleValue'));
    }


    public function searchQuery($searcher = 1, $word = '')
    {

        $artists = DB::table('artist')->get();
        $albums = DB::table('album')
            ->join('artist', 'album.artistid', '=', 'artist.artistid')
            ->selectRaw(DB::raw('artist.name as artist, album.title as title, album.albumid as id'))
            ->get();
        $tracks = DB::table('track')
            ->join('album', 'track.albumid', '=', 'album.albumid')
            ->join('artist', 'album.artistid', '=', 'artist.artistid')
            ->join('mediatype', 'track.mediatypeid', '=', 'mediatype.mediatypeid')
            ->join('genre', 'track.genreid', '=', 'genre.genreid')
//            ->join('users', 'track.added_by', '=', 'users.id')
            ->selectRaw(DB::raw('track.trackid as trackid, track.name as track, album.title as album, artist.name as artist, mediatype.name as media, genre.name as genre, track.composer as composer, track.milliseconds as duration, track.bytes as size, track.unitprice as price'))
            ->where('hidden_status', '!=', '1')
            ->get();


        return view('searchQuery', compact('artists', 'albums', 'tracks'));


    }

    public function search($searcher = 1, $word)
    {
        $word_decoded = urldecode($word);
        $word_decoded = Str::replaceArray('|', ['/'], $word_decoded);

        //Query for search on artists -> Must return the Artist name
        $artists = DB::table('artist')
            ->where('name', $word_decoded)
            ->get();
        $albums = DB::table('album')
            ->join('artist', 'album.artistid', '=', 'artist.artistid')
            ->selectRaw(DB::raw('artist.name as artist, album.title as title, album.albumid as id'))
            ->where('album.title', $word_decoded)
            ->orWhere('artist.name', $word_decoded)
            ->get();
        $tracks = DB::table('track')
            ->join('album', 'track.albumid', '=', 'album.albumid')
            ->join('artist', 'album.artistid', '=', 'artist.artistid')
            ->join('mediatype', 'track.mediatypeid', '=', 'mediatype.mediatypeid')
            ->join('genre', 'track.genreid', '=', 'genre.genreid')
//            ->join('users', 'track.added_by', '=', 'users.id')
            ->selectRaw(DB::raw('track.trackid as trackid, track.name as track, album.title as album, artist.name as artist, mediatype.name as media, genre.name as genre, track.composer as composer, track.milliseconds as duration, track.bytes as size, track.unitprice as price'))
            ->where('hidden_status', '!=', '1')
            ->where('track.name', '=', $word_decoded)
            ->orWhere('album.title', '=', $word_decoded)
            ->orWhere('artist.name', '=', $word_decoded)
            ->orWhere('genre.name', '=', $word_decoded)
            ->orWhere('mediatype.name', '=', $word_decoded)
            ->orWhere('track.composer', '=', $word_decoded)
            ->get();


        return view('searchQuery', compact('artists', 'albums', 'tracks'));


        //Query for search on Albums -> Must return the album name, the album
        //Query for search on Tracks -> Must return the Track, the Album, the artist, the duration, the price
    }

    // Registration
    public function newInfoRegistration()
    {
        $user = Auth::user();
        return view('registration_form', compact('user'));
    }

//    Change user information
    public function userChanges()
    {
//        Authenticate User
        $user = Auth::user();
        $username = $user->name;
        $userQuery = DB::table('users')
            ->join('roles_relations', 'users.id', '=', 'roles_relations.id_user')
            ->where('users.name', $username)
            ->first();
        $userRole = $userQuery->id_roles;


        $userRoles = DB::table('users')
            ->join('roles_relations', 'users.id', '=', 'roles_relations.id_user')
            ->join('roles', 'roles_relations.id_roles', '=', 'roles.id')
            ->selectRaw(DB::raw('users.id as userid, users.name as username, roles.id as roleid, roles.name as rolename'))
            ->orderBy('users.id')
            ->get();

        $roles = DB::table('roles')->get();

        if ($userRole != 1) {
            return view('Error405');
        } else return view('userChanges', compact('userRoles', 'roles'));
    }

    // Statistics
    public function statistics()
    {
        $artist_table = DB::table('artist');
        $album_table = DB::table('album');

//        Top 5 Artists
        $top5Artists = DB::table('artist')
            ->join('album', 'artist.artistid', '=', 'album.artistid')
            ->select(DB::raw(' artist.name, COUNT(album.albumid) as albums'))
            ->groupBy('artist.name')
            ->orderBy('albums', 'desc')
            ->limit(5)
            ->get();

//        Genre with most songs
        $genreMostSongs = DB::table('genre')
            ->join('track', 'track.genreid', '=', 'genre.genreid')
            ->selectRaw(DB::raw('genre.name, COUNT(track.genreid) as counter'))
            ->groupBy('genre.name')
            ->orderBy('counter', 'desc')
            ->limit(5)
            ->get();


//        Playlist Duration
        $playlistDuration = DB::table('playlist')
            ->join('playlisttrack', 'playlist.playlistid', '=', 'playlisttrack.playlistid')
            ->join('track', 'playlisttrack.trackid', '=', 'track.trackid')
            ->selectRaw(DB::raw('playlist.name, sum(track.milliseconds) as duration'))
            ->groupBy('playlist.name')
            ->orderBy('duration', 'desc')
            ->get();


//        Longest Songs
        $longestSongs = DB::table('playlist')
            ->join('playlisttrack', 'playlist.playlistid', '=', 'playlisttrack.playlistid')
            ->join('track', 'playlisttrack.trackid', '=', 'track.trackid')
            ->selectRaw(DB::raw('track.name, sum(track.milliseconds) as duration'))
            ->groupBy('track.name')
            ->orderBy('duration', 'desc')
            ->limit(5)
            ->get();
//        User with the most submitted songs
        $userSubmitted = DB::table('users')
            ->join('track', 'users.id', '=', 'track.added_by')
            ->selectRaw(DB::raw('users.name, count(*) as submitted'))
            ->groupBy('users.name')
            ->orderBy('submitted')
            ->limit(5)
            ->get();


//        Average per Genre
        $avgGenre = DB::table('genre')
            ->join('track', 'track.genreid', '=', 'genre.genreid')
            ->selectRaw(DB::raw('genre.name, round(avg(track.milliseconds), 2) as average'))
            ->groupBy('genre.name')
            ->orderBy('average', 'desc')
            ->get();

//        Quantity per playlist
        $artistPerPlaylist = DB::table('playlist')
            ->join('playlisttrack', 'playlist.playlistid', '=', 'playlisttrack.playlistid')
            ->join('track', 'playlisttrack.trackid', '=', 'track.trackid')
            ->join('album', 'track.albumid', '=', 'album.albumid')
            ->join('artist', 'album.artistid', '=', 'artist.artistid')
            ->selectRaw(DB::raw('DISTINCT playlist.name, COUNT(artist.name) as counter'))
            ->groupBy('playlist.name')
            ->orderBy('counter', 'desc')
            ->get();

//        Genre Diversity
        $genreDiversity = DB::table('artist')
            ->join('album', 'album.artistid', '=', 'artist.artistid')
            ->join('track', 'album.albumid', '=', 'track.albumid')
            ->join('genre', 'track.genreid', '=', 'genre.genreid')
            ->selectRaw(DB::raw('artist.name, COUNT(track.genreid) as counter'))
            ->groupBy('artist.name')
            ->orderBy('counter', 'desc')
            ->limit(5)
            ->get();


        return view('outputFile', compact('top5Artists', 'genreMostSongs', 'longestSongs', 'playlistDuration', 'avgGenre', 'artistPerPlaylist', 'genreDiversity', 'userSubmitted'));
    }

    // Info Register section
    public function registerArtist($artistName)
    {

        $artist_name_formated = urldecode($artistName);
        $artist_name_formated = Str::replaceArray('|', ['/'], $artist_name_formated);
        $artist = new artist;
        $artistCount = DB::table('artist')->orderBy('artistid', 'desc')->first();
        $artistidsum = $artistCount->artistid;


        $artist->artistid = $artistidsum + 1;
        $artist->name = $artist_name_formated;

        $artist->save();
        return redirect()->action('HomeController@profile');
    }

    public function registerAlbum($artist, $album)
    {
        $artist_name_formated = urldecode($artist);
        $artist_name_formated = Str::replaceArray('|', ['/'], $artist_name_formated);
        $album_name_formated = urldecode($album);
        $album_name_formated = Str::replaceArray('|', ['/'], $album_name_formated);

//        Instantiate table
        $album_table = new album;
////        Check how many albums exist already
        $album_count = DB::table('album')->orderBy('artistid', 'desc')->first();
        $albumidsum = $album_count->albumid;
////        Check if the artist exists or not (value must be higher than 0)
        $artistExists = DB::table('artist')->where('name', $artist_name_formated)->count();
////        Get the id of the artist
        $idGetter = DB::table('artist')->where('name', $artist_name_formated)->first();
////        Store the id of the artist
        $idStorer = $idGetter->artistid;
//
        if ($artistExists > 0) {
            $album_table->albumid = $albumidsum + 1;
            $album_table->title = $album_name_formated;
            $album_table->artistid = $idStorer;
            $album_table->save();
        } else {
            print('Artist not found. Couldn\'t add album.');
        }
        return redirect()->action('HomeController@profile');
    }

    public function registerTrack($artist, $album, $track, $genre)
    {

        $user = Auth::user();
//      Get the data from the URL
        $artist_name_formated = urldecode($artist);
        $artist_name_formated = Str::replaceArray('|', ['/'], $artist_name_formated);
        $album_name_formated = urldecode($album);
//        print($album_name_formated);
        $album_name_formated = Str::replaceArray('|', ['/'], $album_name_formated);
//        print($album_name_formated);
        $track_name_formated = urldecode($track);
        $track_name_formated = Str::replaceArray('|', ['/'], $track_name_formated);

        $userid = $user->id;
//        instantiate table
        $track_table = new track();
//
////        ARTIST SECTION
////        check if the artist exists (count must be larger than 0)
        $artistExists = DB::table('artist')->where('name', $artist_name_formated)->count();
        ////        Get the id of the artist
        $id_artist_getter = DB::table('artist')->where('name', $artist_name_formated)->first();
////        Store the ID
//        print('Artist Exists:'.$artistExists);

        if ($id_artist_getter == null) {
            return "Artist not found";
        }
        $id_artist = $id_artist_getter->artistid;
//
////        ALBUM SECTION
////        Check if the album exists or not (count must be larger than 0)
        $albumExists = DB::table('album')->where('title', $album_name_formated)->count();
        print ($albumExists);
        ////        Get the ID of the album
        $id_album_getter = DB::table('album')->where('title', $album_name_formated)->first();
////        Store the value of the ID
        print(var_dump($id_album_getter));
        if ($id_album_getter == null) {
            return "Album not found";
        }
        $id_album = $id_album_getter->albumid;
        print($id_album);
////        Check if the Album belongs to the artist (Count must be larger than 0)
        $albumBelongsToArtist = DB::table('artist')
            ->join('album', 'artist.artistid', '=', 'artist.artistid')
            ->count();
        print ($albumBelongsToArtist);
//
//        TRACK SECTION
//       Count how many tracks there are
        $trackid = DB::table('track')
            ->orderBy('trackid', 'desc')
            ->first();

        $idtrack = $trackid->trackid;


        if ($artistExists > 0) {
            if ($albumExists > 0) {
                if ($albumBelongsToArtist > 0) {
                    $track_table->trackid = $idtrack + 1;
                    $track_table->name = $track_name_formated;
                    $track_table->albumid = $id_album;
                    $track_table->mediatypeid = 1;
                    $track_table->genreid = $genre;
                    $track_table->composer = null;
                    $track_table->milliseconds = rand(80000, 300000);
                    $track_table->bytes = rand(90000, 300000);
                    $track_table->unitprice = 0.99;
                    $track_table->hidden_status = 0;
                    $track_table->added_by = $userid;
                    $track_table->save();
                } else {
                    return "The Album does not belong to the artist";
                }
            } else return "The album doesn't exist";
        } else {
            return "The artist doesn\'t exist";
        }
        return redirect()->action('HomeController@profile');
    }


    // Info Deleting Section
    public function deletePage()
    {
        return view('delete_form');
    }

    public function deleteArtist($artist)
    {
        $artist_name_formated = urldecode($artist);
        $artist_name_formated = Str::replaceArray('|', ['/'], $artist_name_formated);

//        Check if the artist exists
        $artistExists = DB::table('artist')
            ->where('name', $artist_name_formated)
            ->count();

        if ($artistExists < 1) return 'This artist does not exist! (Check your caps)';
//        Get the table that has all the tracks
        $tracks = DB::table('artist')
            ->join('album', 'artist.artistid', '=', 'album.artistid')
            ->join('track', 'album.albumid', '=', 'track.albumid')
            ->where('artist.name', $artist_name_formated)
            ->get();
//        Get the table that has all the albums
        $albums = DB::table('artist')
            ->join('album', 'artist.artistid', '=', 'album.artistid')
            ->where('artist.name', $artist_name_formated)
            ->get();

//        Get the table with the artist name


        $track_table = DB::table('track');
        $album_table = DB::table('album');
        $artist_table = DB::table('artist');

        foreach ($tracks as $track) {
            print($track->trackid . '<br>');
        }
        foreach ($tracks as $track) {
            $id = $track->trackid;
            $track_table->where('trackid', $id)->delete();

        }

        foreach ($albums as $album) {
            $id = $album->albumid;
            $album_table->where('albumid', $id)->delete();

        }

        DB::table('artist')
            ->where('name', $artist_name_formated)->delete();


        return redirect()->action('HomeController@profile');


    }

    public function deleteAlbum($artist, $album)
    {
        $artist_name_formated = urldecode($artist);
        $artist_name_formated = Str::replaceArray('|', ['/'], $artist_name_formated);

        $album_name_formated = urldecode($album);
        $album_name_formated = Str::replaceArray('|', ['/'], $album_name_formated);


//        Check if the album belongs
        $album_belong = DB::table('album')
            ->join('artist', 'album.artistid', '=', 'artist.artistid')
            ->where('album.title', $album_name_formated)
            ->where('artist.name', $artist_name_formated)
            ->count();

        if ($album_belong < 1) return 'Album doesn\'t exist!';

//        Get the album id to delete all the tracks
        $id_album = DB::table('album')
            ->join('artist', 'album.artistid', '=', 'artist.artistid')
            ->where('album.title', $album_name_formated)
            ->where('artist.name', $artist_name_formated)
            ->first();
        $id_album = $id_album->albumid;

        DB::table('track')->where('albumid', $id_album)->delete();
        DB::table('album')->where('title', $album_name_formated)->delete();
        return redirect()->action('HomeController@profile');
    }

    public function deleteTrack($artist, $album, $track)
    {
//        Decode URLs
        $artist_name_formated = urldecode($artist);
        $artist_name_formated = Str::replaceArray('|', ['/'], $artist_name_formated);

        $album_name_formated = urldecode($album);
        $album_name_formated = Str::replaceArray('|', ['/'], $album_name_formated);

        $track_name_formated = urldecode($track);
        $track_name_formated = Str::replaceArray('|', ['/'], $track_name_formated);

//        Instantiate the table
        $trackTable = DB::table('track');

//        Check if track exists
        $trackExists = DB::table('track')->where('name', $track_name_formated)
            ->count();
//        Check if track belongs to Artist
        $trackBelongs = DB::table('track')
            ->join('album', 'track.albumid', '=', 'album.albumid')
            ->join('artist', 'album.artistid', '=', 'artist.artistid')
            ->where('track.name', $track_name_formated)
            ->where('album.title', $album_name_formated)
            ->where('artist.name', $artist_name_formated)
            ->count();

        if ($trackBelongs < 1) return 'This information is invalid.';
//        Delete Track
        $trackTable->where('name', '=', $track_name_formated)
            ->delete();
        return redirect()->action('HomeController@profile');

    }

    // To hide a song
    public function hideSong()
    {
        $user = Auth::user();

        $username = $user->name;


        $userQuery = DB::table('users')
            ->join('roles_relations', 'users.id', '=', 'roles_relations.id_user')
            ->where('users.name', $username)->first();
        $userRole = $userQuery->id_roles;

        if ($userRole != 1) {
            return view('Error405');
        } else return view('hideSongForm');
    }

    public function hideTheSongMethod($artist, $album, $track)
    {
//        Get the data from the URL
        $artist_name = urldecode($artist);
        $artist_name = Str::replaceArray('|', ['/'], $artist_name);
        $album_name = urldecode($album);
//        print($album_name);
        $album_name = Str::replaceArray('|', ['/'], $album_name);
//        print($album_name_formated);
        $track_name = urldecode($track);
        $track_name = Str::replaceArray('|', ['/'], $track_name);

//        Get Artist id
//        $idGetter = DB::table('artist')->where('name', $artist_name)->first();
        $updatingTable = DB::table('artist')
            ->join('album', 'artist.artistid', '=', 'album.artistid')
            ->join('track', 'album.albumid', '=', 'track.albumid')
            ->where('artist.name', $artist_name)
            ->where('album.title', $album_name)
            ->where('track.name', $track_name)->first();


        $id_track = $updatingTable->trackid;
        $finder = track::find($id_track);
        if ($finder->hidden_status == 1) {
            $finder->hidden_status = 0;
            $finder->save();
        } elseif ($finder->hidden_status == 0) {
            $finder->hidden_status = 1;
            $finder->save();
        }
        return redirect()->action('HomeController@profile');

    }

    public function deleteUser($userID)
    {
        $user = Auth::user();

        $username = $user->name;


        $userQuery = DB::table('users')
            ->join('roles_relations', 'users.id', '=', 'roles_relations.id_user')
            ->where('users.name', $username)->first();
        $userRole = $userQuery->id_roles;

        if ($userRole != 1) {
            return view('Error405');
        }
        DB::table('users')->where('id', $userID)->delete();

        return redirect()->action('HomeController@userChanges');


    }


    public function changeRoles()
    {
        $user = Auth::user();

        $username = $user->name;


        $userQuery = DB::table('users')
            ->join('roles_relations', 'users.id', '=', 'roles_relations.id_user')
            ->where('users.name', $username)->first();
        $userRole = $userQuery->id_roles;
        if ($userRole != 1) {
            return view('Error405');
        } else {
            return redirect()->action('HomeController@userChanges');
        }
    }

    public function rolechange($userID, $roleChanged)
    {
        $user = Auth::user();
        $username = $user->name;
        $userQuery = DB::table('users')
            ->join('roles_relations', 'users.id', '=', 'roles_relations.id_user')
            ->where('users.name', $username)->first();
        $userRole = $userQuery->id_roles;

        if ($userRole != 1) {
            return view('Error405');
        }

        $roleTable = new roles_relations;
        $control = DB::table('roles_relations')->where('id_user', $userID)->get();
        DB::table('roles_relations')->where('id_user', $userID)->delete();
        DB::table('roles_relations')->insert(['id_user' => $userID, 'id_roles' => (int)$roleChanged]);
        $control2 = DB::table('roles_relations')->where('id_user', $userID)->get();
        return redirect()->action('HomeController@changeRoles');


    }


//    UPDATE INFORMATION
    public function UpdateInfoEntrance()
    {
        $user = Auth::user();
        $username = $user->name;
        $userQuery = DB::table('users')
            ->join('roles_relations', 'users.id', '=', 'roles_relations.id_user')
            ->where('users.name', $username)->first();
        $userRole = $userQuery->id_roles;
        if ($userRole != 1) return view('Error405');

        return view('updateInfo');
    }


    public function updateArtistInfo($oldArtistName, $newArtistName)
    {

        $old_artist = Str::replaceArray('|', ['/'], $oldArtistName);
        $artist_name_formated = Str::replaceArray('|', ['/'], $newArtistName);


        $user = Auth::user();
        $username = $user->name;
        $userQuery = DB::table('users')
            ->join('roles_relations', 'users.id', '=', 'roles_relations.id_user')
            ->where('users.name', $username)->first();
        $userRole = $userQuery->id_roles;
        if ($userRole != 1) return view('Error405');


        DB::table('artist')->where('name', $old_artist)->update([
            'name' => $artist_name_formated
        ]);
        return view('updateInfo');
    }


    public function updateAlbumInfo($artist, $album, $newAlbum)
    {

        $artist_name_formated = Str::replaceArray('|', ['/'], $artist);
        $album_name_formated = Str::replaceArray('|', ['/'], $album);
        $album_new_name_formated = Str::replaceArray('|', ['/'], $newAlbum);

        $user = Auth::user();
        $username = $user->name;
        $userQuery = DB::table('users')
            ->join('roles_relations', 'users.id', '=', 'roles_relations.id_user')
            ->where('users.name', $username)->first();
        $userRole = $userQuery->id_roles;
        if ($userRole != 1) return view('Error405');


        DB::table('album')
            ->join('artist', 'album.artistid', '=', 'artist.artistid')
            ->where('artist.name', $artist_name_formated)->where('album.title', $album_name_formated)->update([
                'album.title' => $album_new_name_formated
            ]);
        return view('updateInfo');

    }

    public function updateTrackInfo($artist, $album, $track, $newTrack)
    {
        $artist_formated = Str::replaceArray('|', ['/'], $artist);
        $AlbumFormated = Str::replaceArray('|', ['/'], $album);
        $oldTrackFormated = Str::replaceArray('|', ['/'], $track);
        $newTrackFormated = Str::replaceArray('|', ['/'], $newTrack);

        $user = Auth::user();
        $username = $user->name;
        $userQuery = DB::table('users')
            ->join('roles_relations', 'users.id', '=', 'roles_relations.id_user')
            ->where('users.name', $username)->first();
        $userRole = $userQuery->id_roles;
        if ($userRole != 1) return view('Error405');


        $checker = DB::table('track')
            ->join('album', 'album.albumid', '=', 'track.albumid')
            ->join('artist', 'artist.artistid', '=', 'album.artistid')
            ->where('artist.name', $artist_formated)
            ->where('album.title', $AlbumFormated)
            ->where('track.name', $oldTrackFormated)
            ->update([
                'track.name' => $newTrackFormated
            ]);
        return view('updateInfo');

    }
}
