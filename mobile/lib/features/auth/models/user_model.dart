class UserModel {
  final int id;
  final String name;
  final String email;
  final String? avatarPath;
  final String theme;

  UserModel({
    required this.id,
    required this.name,
    required this.email,
    this.avatarPath,
    this.theme = 'light',
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'] as int,
      name: json['name'] as String,
      email: json['email'] as String,
      avatarPath: json['avatar_path'] as String?,
      theme: json['theme'] as String? ?? 'light',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'avatar_path': avatarPath,
      'theme': theme,
    };
  }
}

