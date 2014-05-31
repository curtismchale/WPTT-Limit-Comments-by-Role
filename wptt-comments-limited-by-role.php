<?php
/*
Plugin Name: WPTT Limited Comments
Plugin URI: http://csapsociety.bc.ca
Description: Limits comments by user role.
Version: 1.0
Author: SFNdesign, Curtis McHale
Author URI: http://sfndesign.ca
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

class WPTT_Limit_Comments{

	function __construct(){

		add_filter( 'comments_array', array( $this, 'limit_comments' ), 10, 2 );
		add_filter( 'the_comments', array( $this, 'limit_comments' ), 10, 2 );

		add_filter( 'get_comments_number', array( $this, 'proper_number' ), 10, 2 );

	} // construct

	/**
	 * Adjusts the number of comments to what the current user should actually see
	 * @since 1.0
	 * @author SFNdesign, Curtis McHale
	 * @access public
	 *
	 * @param int       $count      required        The number of comments
	 * @param int       $post_id    required        id of the current post
	 * @uses get_comments()                         Returns comments given arg
	 * @uses $this->limit_comments()                Returns an array of comments filtered by what we can actually see
	 * @uses count()                                Returns the number of items in our array
	 * @return int      $number                     The number of comments we can actually see
	 */
	public function proper_number( $count, $post_id ){

		if ( ! $this->should_we_limit_comments( $post_id ) ) return $count;

		$comments = get_comments( array( 'post_id' => absint( $post_id ) ) );

		$filtered_comments = $this->limit_comments( $comments, $post_id );

		$number = count( $filtered_comments );

		return absint( $number );

	} // proper_number

	/**
	 * Limits the comments based on the user role
	 * @since 1.0
	 * @author SFNdesign, Curtis McHale
	 * @access public
	 *
	 * @param array     $comments       required            Array of comments for post
	 * @param int       $post_id        required            post_id for post that comments go with
	 * @uses $this->get_user_role()                         Returns user role for current user or for user_id given
	 * @uses $this->filter_comments()                       Filters comments based on role matching or if comment was made by admin
	 * @return array    $comments                           Our filtered comments
	 */
	public function limit_comments( $comments, $post_id ){

		if ( ! $this->should_we_limit_comments( $post_id ) ) return $comments;

		$current_user_role = $this->get_user_role();

		$comments = $this->filter_comments( $current_user_role, $comments );

		return apply_filters( 'wptt_limited_comments_return', $comments, $post_id );

	} // limit_comments

	/**
	 * Returns true if we should actually limit the comments
	 * @since 1.0
	 * @author SFNdesign, Curtis McHale
	 * @access private
	 *
	 * @param int   $post_id    required                    The post_id for the post we are checking
	 * @uses current_user_can()                             Returns true if current user has given capability
	 * @uses get_post_type()                                Returns registered post type name given post_id or post object
	 * @uses is_admin()                                     Returns true if in the WP admin
	 * @return bool                                         True if we should limit the comments
	 */
	private function should_we_limit_comments( $post_id ){

		if ( current_user_can( 'activate_plugins' ) ) $should = false; // admins see all

		if ( ! isset( $should ) ) $should = true;

		return apply_filters( 'wptt_should_we_limit', $should, $post_id );

	}  // should_we_limit_comments

	/**
	 * Filters the comments
	 *
	 * If your role matches or if the commenter is a site admin then the comment should not be filtered
	 * @since 1.0
	 * @author SFNdesign, Curtis McHale
	 *
	 * @param int       $current_user_role      required        The role for the current_user
	 * @param array     $comments               required        The array of comments to filter
	 * @uses get_user_by()                                      Returns user object given field and value
	 * @uses $this->allow_admin_comments()                      Returns comments by admin to our comment array
	 * @uses $this->get_user_by_role()                          Returns the role for a user defaults to current user if not provided with user_id
	 * @return array    $filtered_comments                      Our filtered array of comments
	 */
	private function filter_comments( $current_user_role, $comments ){

		$filtered_comments = array();

		foreach( $comments as $c ){

			$comment_user = get_user_by( 'email', $c->comment_author_email );

			if ( $c->comment_approved != 1 && ! is_admin() ) continue; // skip comments that are not approved only if we are not in the WP Admin

			$filtered_comments = $this->allow_admin_comments( $current_user_role, $comment_user, $c );

			$comment_user_role = $this->get_user_role( $comment_user->ID );

			if ( $current_user_role === $comment_user_role ){
				$filtered_comments[] = $c;
			}

		} // foreach

		return $filtered_comments;

	} // filter_comments

	/**
	 * Filters comments by user role
	 * @since 1.0
	 * @author SFNdesign, Curtis McHale
	 * @access private
	 *
	 * @param object    $current_user_role      required            WP_User Object for the currently signed in user
	 * @param object    $comment_user           required            WP_User Object for the commenter
	 * @param array     $c                      required            The comment we are currently dealing with
	 * @uses user_can()                                             Returns true if given user_id has the given capability
	 * @return $allowed_admin_comments                              Array of comments allowed through
	 */
	private function allow_admin_comments( $current_user_role, $comment_user, $c ){

		$allow_admin            = apply_filters( 'wptt_allow_admin', true );
		$allowed_admin_comments = array();


		if ( user_can( $comment_user->ID, 'activate_plugins' ) && $allow_admin ){
			$allowed_admin_comments[] = $c;
			continue;
		}

		return $allowed_admin_comments;

	} // filter_by_role

	/**
	 * Returns the role for the current user
	 * @since 1.0
	 * @author SFNdesign, Curtis McHale
	 *
	 * @param int   $user_id    optional            The ID of the user we want a role for gets user_id for current user if not provided
	 * @uses
	 * @return int                                  Role for the user
	 */
	private function get_user_role( $user_id = NULL ){

		if ( ! isset( $user_id ) ){
			$current_user = wp_get_current_user();
			$user_id = $current_user->ID;
		}

		$user = new WP_User( absint( $user_id ) );

		return $user->roles[0];

	} // get_user_role

} // WPTT_Limit_Comments

new WPTT_Limit_Comments();
