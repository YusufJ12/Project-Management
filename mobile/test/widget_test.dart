import 'package:flutter_test/flutter_test.dart';
import 'package:nexsphere_mobile/main.dart';

void main() {
  testWidgets('NexSphereApp smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(const NexSphereApp());
    expect(find.text('NexSphere'), findsWidgets);
    await tester.pump(const Duration(milliseconds: 700));
    await tester.pump();
  });
}
