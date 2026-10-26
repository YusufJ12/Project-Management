class UserModel {
  final int id;
  final String name;
  final String email;
  final String? avatarPath;
  final String theme;
  final String? role;

  UserModel({
    required this.id,
    required this.name,
    required this.email,
    this.avatarPath,
    this.theme = 'light',
    this.role,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    String? role;
    if (json['role'] is String) {
      role = json['role'] as String;
    } else if (json['pivot'] is Map && json['pivot']['role'] is String) {
      role = json['pivot']['role'] as String;
    }

    return UserModel(
      id: json['id'] as int,
      name: json['name'] as String,
      email: json['email'] as String,
      avatarPath: json['avatar_path'] as String?,
      theme: json['theme'] as String? ?? 'light',
      role: role,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'avatar_path': avatarPath,
      'theme': theme,
      if (role != null) 'role': role,
    };
  }
}

