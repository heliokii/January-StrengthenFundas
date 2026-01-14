using System;
using System.Collections.Generic;

class Result
{
    /*
     * Complete the 'countApplesAndOranges' function below.
     *
     * The function accepts following parameters:
     *  1. INTEGER s
     *  2. INTEGER t
     *  3. INTEGER a
     *  4. INTEGER b
     *  5. INTEGER_ARRAY apples
     *  6. INTEGER_ARRAY oranges
     */

    public static void countApplesAndOranges(int s, int t, int a, int b, List<int> apples, List<int> oranges)
    {
        int appleCount = 0;
        int orangeCount = 0;

        // Check each apple
        foreach (int distance in apples)
        {
            int position = a + distance;
            if (position >= s && position <= t)
                appleCount++;
        }

        // Check each orange
        foreach (int distance in oranges)
        {
            int position = b + distance;
            if (position >= s && position <= t)
                orangeCount++;
        }

        // Output the result
        Console.WriteLine(appleCount);
        Console.WriteLine(orangeCount);
    }
}