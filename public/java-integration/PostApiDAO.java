package org.example.dao;

import org.example.model.Post;
import org.example.model.Comment;
import java.io.*;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.ArrayList;
import java.util.List;

/**
 * PostDAO via REST API Symfony — TalentFlow Integration (Forum)
 *
 * Ce DAO permet à l'application JavaFX de gérer les posts et commentaires
 * du forum TalentFlow via l'API REST Symfony.
 *
 * Endpoints disponibles :
 *   GET    /api/integration/posts              → liste des posts
 *   GET    /api/integration/posts/{id}         → détail post + commentaires
 *   POST   /api/integration/posts              → créer un post
 *   PUT    /api/integration/posts/{id}         → modifier un post
 *   DELETE /api/integration/posts/{id}         → supprimer un post
 *   GET    /api/integration/posts/{id}/comments → commentaires d'un post
 *   POST   /api/integration/posts/{id}/comments → ajouter un commentaire
 *   DELETE /api/integration/comments/{id}      → supprimer un commentaire
 *
 * IMPORTANT: Démarrer le serveur Symfony avant d'utiliser ce DAO:
 *   php -S 127.0.0.1:8000 -t public
 */
public class PostApiDAO {

    private static final String BASE_URL = "http://127.0.0.1:8000/api/integration";
    private static final String API_KEY  = "talentflow-java-api-key-2026";

    // ===================== READ ALL POSTS =====================

    public List<Post> readAll() {
        List<Post> posts = new ArrayList<>();
        try {
            HttpURLConnection conn = openConnection(BASE_URL + "/posts", "GET");
            int code = conn.getResponseCode();
            if (code != 200) {
                System.err.println("❌ readAll HTTP " + code);
                return posts;
            }

            String json = readResponse(conn);
            // Format: {"posts":[{...},{...}],"total":N}
            String arrayPart = extractJsonArray(json, "posts");
            if (arrayPart == null) return posts;

            for (String obj : splitJsonObjects(arrayPart)) {
                Post p = parsePost(obj);
                if (p != null) posts.add(p);
            }
        } catch (Exception e) {
            System.err.println("❌ Erreur readAll posts : " + e.getMessage());
        }
        return posts;
    }

    // ===================== READ ONE POST =====================

    public Post readById(int id) {
        try {
            HttpURLConnection conn = openConnection(BASE_URL + "/posts/" + id, "GET");
            int code = conn.getResponseCode();
            if (code == 404) return null;
            if (code != 200) {
                System.err.println("❌ readById HTTP " + code);
                return null;
            }
            return parsePost(readResponse(conn));
        } catch (Exception e) {
            System.err.println("❌ Erreur readById post : " + e.getMessage());
            return null;
        }
    }

    // ===================== CREATE POST =====================

    /**
     * Crée un post sur Symfony.
     * @param title    Titre du post
     * @param content  Contenu du post (peut être null)
     * @param authorId ID de l'auteur (utilisateur connecté)
     * @return true si succès
     */
    public boolean create(String title, String content, int authorId) {
        try {
            HttpURLConnection conn = openConnection(BASE_URL + "/posts", "POST");
            conn.setDoOutput(true);
            conn.setRequestProperty("Content-Type", "application/json");

            String json = "{\"title\":" + jsonString(title)
                    + ",\"content\":" + (content != null ? jsonString(content) : "null")
                    + ",\"authorId\":" + authorId + "}";

            try (OutputStream os = conn.getOutputStream()) {
                os.write(json.getBytes("UTF-8"));
            }

            int code = conn.getResponseCode();
            if (code == 201) {
                System.out.println("✅ Post créé via Symfony.");
                return true;
            } else {
                System.err.println("❌ Erreur création post HTTP " + code + " : " + readResponse(conn));
                return false;
            }
        } catch (Exception e) {
            System.err.println("❌ Erreur create post : " + e.getMessage());
            return false;
        }
    }

    // ===================== UPDATE POST =====================

    public boolean update(int id, String title, String content) {
        try {
            HttpURLConnection conn = openConnection(BASE_URL + "/posts/" + id, "PUT");
            conn.setDoOutput(true);
            conn.setRequestProperty("Content-Type", "application/json");

            String json = "{\"title\":" + jsonString(title)
                    + ",\"content\":" + (content != null ? jsonString(content) : "null") + "}";

            try (OutputStream os = conn.getOutputStream()) {
                os.write(json.getBytes("UTF-8"));
            }

            int code = conn.getResponseCode();
            if (code == 200) {
                System.out.println("✅ Post #" + id + " mis à jour.");
                return true;
            } else {
                System.err.println("❌ Erreur update post HTTP " + code);
                return false;
            }
        } catch (Exception e) {
            System.err.println("❌ Erreur update post : " + e.getMessage());
            return false;
        }
    }

    // ===================== DELETE POST =====================

    public boolean delete(int id) {
        try {
            HttpURLConnection conn = openConnection(BASE_URL + "/posts/" + id, "DELETE");
            int code = conn.getResponseCode();
            if (code == 200) {
                System.out.println("✅ Post #" + id + " supprimé.");
                return true;
            } else {
                System.err.println("❌ Erreur delete post HTTP " + code);
                return false;
            }
        } catch (Exception e) {
            System.err.println("❌ Erreur delete post : " + e.getMessage());
            return false;
        }
    }

    // ===================== COMMENTS =====================

    public List<Comment> getComments(int postId) {
        List<Comment> comments = new ArrayList<>();
        try {
            HttpURLConnection conn = openConnection(BASE_URL + "/posts/" + postId + "/comments", "GET");
            int code = conn.getResponseCode();
            if (code != 200) return comments;

            String json = readResponse(conn);
            String arrayPart = extractJsonArray(json, "comments");
            if (arrayPart == null) return comments;

            for (String obj : splitJsonObjects(arrayPart)) {
                Comment c = parseComment(obj);
                if (c != null) comments.add(c);
            }
        } catch (Exception e) {
            System.err.println("❌ Erreur getComments : " + e.getMessage());
        }
        return comments;
    }

    public boolean addComment(int postId, String content, int authorId) {
        try {
            HttpURLConnection conn = openConnection(BASE_URL + "/posts/" + postId + "/comments", "POST");
            conn.setDoOutput(true);
            conn.setRequestProperty("Content-Type", "application/json");

            String json = "{\"content\":" + jsonString(content)
                    + ",\"authorId\":" + authorId + "}";

            try (OutputStream os = conn.getOutputStream()) {
                os.write(json.getBytes("UTF-8"));
            }

            int code = conn.getResponseCode();
            if (code == 201) {
                System.out.println("✅ Commentaire ajouté.");
                return true;
            } else {
                System.err.println("❌ Erreur addComment HTTP " + code);
                return false;
            }
        } catch (Exception e) {
            System.err.println("❌ Erreur addComment : " + e.getMessage());
            return false;
        }
    }

    public boolean deleteComment(int commentId) {
        try {
            HttpURLConnection conn = openConnection(BASE_URL + "/comments/" + commentId, "DELETE");
            int code = conn.getResponseCode();
            return code == 200;
        } catch (Exception e) {
            System.err.println("❌ Erreur deleteComment : " + e.getMessage());
            return false;
        }
    }

    // ===================== HELPERS =====================

    private HttpURLConnection openConnection(String urlStr, String method) throws IOException {
        HttpURLConnection conn = (HttpURLConnection) new URL(urlStr).openConnection();
        conn.setRequestMethod(method);
        conn.setRequestProperty("X-API-KEY", API_KEY);
        conn.setRequestProperty("Accept", "application/json");
        conn.setConnectTimeout(5000);
        conn.setReadTimeout(10000);
        return conn;
    }

    private String readResponse(HttpURLConnection conn) throws IOException {
        InputStream is;
        try {
            is = conn.getInputStream();
        } catch (IOException e) {
            is = conn.getErrorStream();
        }
        if (is == null) return "";
        try (BufferedReader br = new BufferedReader(new InputStreamReader(is, "UTF-8"))) {
            StringBuilder sb = new StringBuilder();
            String line;
            while ((line = br.readLine()) != null) sb.append(line);
            return sb.toString();
        }
    }

    private Post parsePost(String json) {
        if (json == null || json.isEmpty()) return null;
        try {
            Post p = new Post();
            p.setId(parseIntField(json, "id"));
            p.setTitle(parseStringField(json, "title"));
            p.setContent(parseStringField(json, "content"));
            p.setAuthorId(parseIntField(json, "authorId"));
            p.setAuthorNom(parseStringField(json, "authorNom"));
            p.setAuthorPrenom(parseStringField(json, "authorPrenom"));
            p.setUpvotes(parseIntField(json, "upvotes"));
            p.setCommentCount(parseIntField(json, "commentCount"));
            p.setCreatedAt(parseStringField(json, "createdAt"));
            return p;
        } catch (Exception e) {
            System.err.println("❌ Erreur parsePost : " + e.getMessage());
            return null;
        }
    }

    private Comment parseComment(String json) {
        if (json == null || json.isEmpty()) return null;
        try {
            Comment c = new Comment();
            c.setId(parseIntField(json, "id"));
            c.setContent(parseStringField(json, "content"));
            c.setAuthorId(parseIntField(json, "authorId"));
            c.setAuthorName(parseStringField(json, "authorName"));
            c.setPostId(parseIntField(json, "postId"));
            c.setCreatedAt(parseStringField(json, "createdAt"));
            return c;
        } catch (Exception e) {
            return null;
        }
    }

    private String extractJsonArray(String json, String key) {
        String search = "\"" + key + "\":[";
        int start = json.indexOf(search);
        if (start < 0) return null;
        start += search.length() - 1;
        int depth = 0;
        int end = start;
        for (int i = start; i < json.length(); i++) {
            if (json.charAt(i) == '[') depth++;
            else if (json.charAt(i) == ']') { depth--; if (depth == 0) { end = i; break; } }
        }
        return json.substring(start + 1, end);
    }

    private List<String> splitJsonObjects(String arrayContent) {
        List<String> objects = new ArrayList<>();
        int depth = 0; int start = -1;
        for (int i = 0; i < arrayContent.length(); i++) {
            char c = arrayContent.charAt(i);
            if (c == '{') { if (depth == 0) start = i; depth++; }
            else if (c == '}') { depth--; if (depth == 0 && start >= 0) { objects.add(arrayContent.substring(start, i + 1)); start = -1; } }
        }
        return objects;
    }

    private String parseStringField(String json, String key) {
        String search = "\"" + key + "\":\"";
        int start = json.indexOf(search);
        if (start < 0) return null;
        start += search.length();
        int end = json.indexOf("\"", start);
        if (end < 0) return null;
        return json.substring(start, end).replace("\\\"", "\"").replace("\\n", "\n");
    }

    private int parseIntField(String json, String key) {
        String search = "\"" + key + "\":";
        int start = json.indexOf(search);
        if (start < 0) return 0;
        start += search.length();
        int end = start;
        while (end < json.length() && (Character.isDigit(json.charAt(end)) || json.charAt(end) == '-')) end++;
        String val = json.substring(start, end).trim();
        try { return Integer.parseInt(val); } catch (Exception e) { return 0; }
    }

    private String jsonString(String s) {
        if (s == null) return "null";
        return "\"" + s.replace("\\", "\\\\").replace("\"", "\\\"").replace("\n", "\\n") + "\"";
    }
}
